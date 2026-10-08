<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\StudentNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Paying a payment online: starting an attempt, following it to the end, and
 * settling the payment exactly once, whether Fawry tells us by notification or
 * we ask it. Both routes end in {@see self::applyStatus}.
 */
class OnlinePayments
{
    /** A Pay at Fawry reference number that the customer pays at an outlet. */
    public const FAWRY = 'fawry';

    /** An e-wallet request to a Vodafone Cash number, through Fawry. */
    public const VODAFONE_CASH = 'vodafone_cash';

    public const METHODS = [self::FAWRY, self::VODAFONE_CASH];

    public function __construct(
        private readonly FawryClient $fawry,
        private readonly StudentNotifier $notifier,
    ) {
    }

    /** Vodafone Cash numbers are Vodafone Egypt lines: 010 and eight digits. */
    public static function isVodafoneNumber(string $mobile): bool
    {
        return (bool) preg_match('/^010\d{8}$/', $mobile);
    }

    /** `01xxxxxxxxx` from the ways a number gets typed (+20, 0020, spaces). */
    public static function normalizeMobile(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);
        $digits = preg_replace('/^(0020|20)/', '0', $digits);

        return preg_match('/^01[0125]\d{8}$/', $digits) ? $digits : null;
    }

    /**
     * Starts paying [$payment]; an open attempt of the same kind is reused so
     * a double tap never creates two requests.
     *
     * @param  ?string  $walletMobile  the Vodafone Cash number, for that method
     * @param  ?string  $customerMobile  a phone for the receipt; the family's by default
     */
    public function start(Payment $payment, string $method, ?string $walletMobile, ?string $customerMobile, User $by): PaymentAttempt
    {
        if (! $this->fawry->isConfigured()) {
            throw ValidationException::withMessages(['method' => 'الدفع الإلكتروني غير مفعّل بعد. اطلب من الإدارة ربط حساب فوري.']);
        }
        if ($payment->status === 'paid') {
            throw ValidationException::withMessages(['payment' => 'هذه الدفعة مدفوعة بالفعل.']);
        }
        if ($method === self::VODAFONE_CASH) {
            $walletMobile = self::normalizeMobile($walletMobile);
            if ($walletMobile === null || ! self::isVodafoneNumber($walletMobile)) {
                throw ValidationException::withMessages(['wallet_mobile' => 'أدخل رقم فودافون كاش صحيحاً يبدأ بـ 010.']);
            }
        } else {
            $walletMobile = null;
        }

        $open = $payment->attempts()
            ->where('provider', 'fawry')->where('method', $method)->where('wallet_mobile', $walletMobile)
            ->where('status', PaymentAttempt::PENDING)->latest('id')->get()
            ->first(fn (PaymentAttempt $attempt) => $attempt->isOpen());
        if ($open) {
            return $open;
        }

        $student = $payment->student;
        $mobile = $walletMobile
            ?? self::normalizeMobile($customerMobile)
            ?? self::normalizeMobile($student->parent_phone)
            ?? self::normalizeMobile($student->phone);
        if ($mobile === null) {
            throw ValidationException::withMessages(['customer_mobile' => 'أضف رقم هاتف صحيحاً لولي الأمر في ملف الطالب أولاً.']);
        }

        $expiresAt = now()->addHours((int) config('services.fawry.expiry_hours', 48));
        $attempt = DB::transaction(function () use ($payment, $method, $walletMobile, $by, $expiresAt) {
            $attempt = PaymentAttempt::create([
                'payment_id' => $payment->id,
                'provider' => 'fawry',
                'method' => $method,
                'merchant_ref' => 'tmp'.Str::random(12),
                'wallet_mobile' => $walletMobile,
                'amount' => $payment->amount,
                'expires_at' => $expiresAt,
                'requested_by' => $by->id,
            ]);
            // Numeric and unique for the merchant: Fawry's wallet API expects a number.
            $attempt->update(['merchant_ref' => now()->format('ymdHis').str_pad((string) $attempt->id, 6, '0', STR_PAD_LEFT)]);

            return $attempt;
        });

        $order = [
            'ref' => $attempt->merchant_ref,
            'amount' => $payment->amount,
            'description' => 'مصروفات '.$student->name,
            'mobile' => $mobile,
            'email' => $this->emailFor($by->isAnyRole('student', 'parent') ? $by->email : null),
            'name' => $student->name,
        ];
        try {
            $response = $method === self::VODAFONE_CASH
                ? $this->fawry->requestWalletPayment($order, (string) $walletMobile, $expiresAt)
                : $this->fawry->createReference($order, $expiresAt);
        } catch (\Throwable $error) {
            // Nothing was requested at Fawry, so the attempt is not left pending.
            $attempt->update(['status' => PaymentAttempt::FAILED]);
            throw $error;
        }
        $attempt->update(['reference_number' => $response['referenceNumber'] ?? null]);

        // The same instance, so the caller can still tell it was just created.
        return $attempt->refresh();
    }

    /** Asks Fawry where an open attempt stands, in case its notification was missed. */
    public function refresh(PaymentAttempt $attempt): PaymentAttempt
    {
        if ($attempt->status !== PaymentAttempt::PENDING || ! $this->fawry->isConfigured()) {
            return $attempt;
        }
        $json = $this->fawry->status($attempt->merchant_ref);
        $status = (string) ($json['paymentStatus'] ?? $json['orderStatus'] ?? '');
        if ($status !== '') {
            $this->applyStatus($attempt, $status, $json['paymentAmount'] ?? $json['orderAmount'] ?? null);
        } elseif ($attempt->expires_at?->isPast()) {
            $this->applyStatus($attempt, 'EXPIRED', null);
        }

        return $attempt->fresh();
    }

    /**
     * Handles a Server To Server Notification V2. The caller has already
     * checked the signature.
     *
     * @param  array<string, mixed>  $notification
     */
    public function applyNotification(array $notification): void
    {
        $ref = (string) ($notification['merchantRefNumber'] ?? $notification['merchantRefNum'] ?? '');
        $attempt = PaymentAttempt::query()->where('merchant_ref', $ref)->first();
        if ($attempt) {
            $this->applyStatus(
                $attempt,
                (string) ($notification['orderStatus'] ?? ''),
                $notification['paymentAmount'] ?? null,
                $notification['fawryRefNumber'] ?? null,
            );
        }
    }

    /**
     * Moves an attempt to where Fawry says it is. Paid settles the payment
     * once, and only for the full amount; repeats change nothing.
     */
    public function applyStatus(PaymentAttempt $attempt, string $fawryStatus, int|float|string|null $paidAmount, ?string $fawryRef = null): void
    {
        $status = match (strtoupper($fawryStatus)) {
            'PAID' => PaymentAttempt::PAID,
            'EXPIRED' => PaymentAttempt::EXPIRED,
            'CANCELED', 'CANCELLED' => PaymentAttempt::CANCELED,
            'FAILED' => PaymentAttempt::FAILED,
            default => null, // NEW, UNPAID, refunds…: nothing to do here
        };
        if ($status === null) {
            return;
        }

        DB::transaction(function () use ($attempt, $status, $paidAmount, $fawryRef): void {
            $locked = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === PaymentAttempt::PAID) {
                return;
            }
            if ($fawryRef && ! $locked->reference_number) {
                $locked->reference_number = $fawryRef;
            }
            if ($status !== PaymentAttempt::PAID) {
                if ($locked->status === PaymentAttempt::PENDING) {
                    $locked->status = $status;
                }
                $locked->save();

                return;
            }
            // A short payment does not settle the dues.
            if ($paidAmount !== null && round((float) $paidAmount, 2) < round((float) $locked->amount, 2)) {
                $locked->save();

                return;
            }

            $locked->forceFill(['status' => PaymentAttempt::PAID, 'paid_at' => now()])->save();
            $payment = Payment::query()->whereKey($locked->payment_id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== 'paid') {
                $payment->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'note' => trim(($payment->note ? $payment->note.' · ' : '').'دفع إلكتروني ('.$this->label($locked->method).') مرجع '.$locked->merchant_ref),
                ]);
                $student = $payment->student;
                $this->notifier->notify(
                    [$student->id],
                    'تأكيد دفع',
                    "تم استلام {$payment->amount} ج.م لـ {$student->name} عبر ".$this->label($locked->method).'.',
                    'payment',
                );
            }
        });
    }

    private function label(string $method): string
    {
        return $method === self::VODAFONE_CASH ? 'فودافون كاش' : 'فوري';
    }

    private function emailFor(?string $email): string
    {
        return $email ?: (string) config('services.fawry.fallback_email');
    }
}
