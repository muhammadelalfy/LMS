<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Student;

class StudentLedger
{
    /**
     * Unpaid (pending or overdue) payments for a student, oldest due first.
     *
     * @return array{outstanding_payments: \Illuminate\Support\Collection<int, Payment>, outstanding_total: int}
     */
    public function outstanding(Student $student): array
    {
        $payments = Payment::query()
            ->where('student_id', $student->id)
            ->whereIn('status', ['pending', 'overdue'])
            ->orderBy('due_at')
            ->get();

        return [
            'outstanding_payments' => $payments,
            'outstanding_total' => (int) $payments->sum('amount'),
        ];
    }
}
