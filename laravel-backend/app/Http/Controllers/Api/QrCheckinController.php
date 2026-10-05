<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesStaff;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Student;
use App\Services\StudentLedger;
use App\Services\StudentNotifier;
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

    public function __construct(
        private readonly StudentLedger $ledger,
        private readonly StudentNotifier $notifier,
    ) {
    }

    public function payment(Request $request)
    {
        $this->authorizeStaff($request);
        $data = $request->validate([
            'payload' => 'required|string|min:32|max:96',
            'payment_id' => 'nullable|integer',
            'amount' => 'required_without:payment_id|nullable|integer|min:1',
            'note' => 'nullable|string|max:255',
        ]);

        $student = Student::query()->where('qr_token', $data['payload'])->first();
        if (! $student) {
            throw ValidationException::withMessages(['payload' => 'رمز QR غير صالح لهذا الطالب.']);
        }

        $payment = DB::transaction(function () use ($data, $student, $request): Payment {
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
                    'paid_at' => now(),
                    'note' => trim(($payment->note ? $payment->note.' · ' : '').($data['note'] ?? 'تأكيد عبر QR')),
                ]);

                return $payment;
            }

            return Payment::create([
                'student_id' => $student->id,
                'amount' => $data['amount'],
                'status' => 'paid',
                'due_at' => now(),
                'paid_at' => now(),
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
}
