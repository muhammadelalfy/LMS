<?php

namespace Modules\Payments\Http\Controllers;

use Modules\Core\Http\Concerns\AuthorizesStaff;
use App\Http\Controllers\Controller;
use Modules\Payments\Models\Payment;
use Modules\Students\Models\Student;
use Modules\Payments\Services\StudentLedger;
use Modules\Notifications\Services\StudentNotifier;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payment confirmation at the front desk: staff scan the student's QR card
 * and either confirm an outstanding payment or collect a new one. Requiring
 * the QR payload ties every confirmation to the physically presented card.
 */
class QrCheckinController extends Controller
{
    use AuthorizesStaff;

    /** How many days back a payment the phone kept offline is dated to its own time. */
    private const REPLAY_DAYS = 30;

    public function __construct(
        private readonly StudentLedger $ledger,
        private readonly StudentNotifier $notifier,
    ) {
    }

    /**
     * Front-desk payment lookup: the student behind a QR card and their unpaid
     * dues, without recording attendance.
     */
    public function lookup(Request $request)
    {
        $this->authorizeStaff($request);
        $payload = $request->validate(['payload' => 'required|string|min:32|max:96'])['payload'];

        $student = Student::query()->where('qr_token', $payload)->first();
        if (! $student) {
            throw ValidationException::withMessages(['payload' => 'رمز QR غير صالح لهذا الطالب.']);
        }

        return response()->json([
            'student' => ['id' => $student->id, 'name' => $student->name, 'grade' => $student->grade, 'group' => $student->group],
            ...$this->ledger->outstanding($student),
        ]);
    }

    public function payment(Request $request)
    {
        $this->authorizeStaff($request);
        $data = $request->validate([
            'payload' => 'required|string|min:32|max:96',
            'payment_id' => 'nullable|integer',
            'amount' => 'required_without:payment_id|nullable|integer|min:1',
            'note' => 'nullable|string|max:255',
            'occurred_at' => 'nullable|date',
        ]);

        $student = Student::query()->where('qr_token', $data['payload'])->first();
        if (! $student) {
            throw ValidationException::withMessages(['payload' => 'رمز QR غير صالح لهذا الطالب.']);
        }
        $when = $this->receivedAt($request, $data['occurred_at'] ?? null);

        $payment = DB::transaction(function () use ($data, $student, $request, $when): Payment {
            if (! empty($data['payment_id'])) {
                $payment = Payment::query()
                    ->whereKey($data['payment_id'])
                    ->where('student_id', $student->id)
                    ->lockForUpdate()
                    ->first();
                if (! $payment) {
                    throw ValidationException::withMessages(['payment_id' => 'هذه الدفعة لا تخص الطالب صاحب الرمز.']);
                }
                if ($payment->status === 'paid') {
                    throw ValidationException::withMessages(['payment_id' => 'تم تأكيد هذه الدفعة مسبقاً.']);
                }
                $payment->update([
                    'status' => 'paid',
                    'paid_at' => $when,
                    'note' => trim(($payment->note ? $payment->note.' · ' : '').($data['note'] ?? 'تأكيد عبر QR')),
                ]);

                return $payment;
            }

            return Payment::create([
                'student_id' => $student->id,
                'amount' => $data['amount'],
                'status' => 'paid',
                'due_at' => $when,
                'paid_at' => $when,
                'note' => $data['note'] ?? 'تحصيل عبر QR',
                'recorded_by' => $request->user()->id,
            ]);
        });

        $this->notifier->notify(
            [$student->id],
            'تأكيد دفع',
            "تم استلام {$payment->amount} ج.م لـ {$student->name}.",
            'payment',
        );

        return response()->json([
            'payment' => $payment->fresh('student'),
            'student' => ['id' => $student->id, 'name' => $student->name, 'grade' => $student->grade, 'group' => $student->group],
            ...$this->ledger->outstanding($student),
        ], $payment->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * When the money changed hands. A payment the phone kept while offline and
     * is now sending (X-Offline-Replay) carries the time it was taken; any
     * other request, or a time that cannot be right, is stamped now.
     */
    private function receivedAt(Request $request, ?string $value): CarbonInterface
    {
        if ($value === null || $request->header('X-Offline-Replay') !== '1') {
            return now();
        }
        $time = CarbonImmutable::parse($value);
        $tooOld = $time->lessThan(now()->subDays(self::REPLAY_DAYS));
        $inTheFuture = $time->greaterThan(now()->addHours(36));

        return $tooOld || $inTheFuture ? now() : $time;
    }
}
