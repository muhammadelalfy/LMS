<?php

namespace Modules\Payments\Policies;

use Modules\Auth\Models\User;
use Modules\Groups\Models\ClassGroup;
use Modules\Payments\Models\Payment;
use Modules\Students\Models\Student;

/**
 * Who may write a student's payment record (ADR 0002): admin, or a teacher
 * whose groups include the payment's student — except `delete`, which stays
 * admin-only: removing a payment can hide a cash shortfall, and the normal
 * correction path is a status transition, not deletion (§11).
 */
class PaymentPolicy
{
    public function create(User $user, array $data = []): bool
    {
        if ($user->isAnyRole('admin')) {
            return true;
        }
        if (! $user->isAnyRole('teacher')) {
            return false;
        }
        $student = Student::query()->find($data['student_id'] ?? null);

        return $student !== null && $this->groupOwned($user, $student->group);
    }

    public function update(User $user, Payment $payment): bool
    {
        if ($user->isAnyRole('admin')) {
            return true;
        }

        return $user->isAnyRole('teacher') && $this->groupOwned($user, $payment->student?->group);
    }

    /** Admin-only — deleting a payment can hide a cash shortfall (ADR 0002). */
    public function delete(User $user, Payment $payment): bool
    {
        return $user->isAnyRole('admin');
    }

    /** A group name a teacher may act on: theirs, unassigned, or unknown to Groups at all. */
    private function groupOwned(User $user, ?string $groupName): bool
    {
        return $groupName !== null && ! in_array($groupName, ClassGroup::namesClaimedByOthers($user), true);
    }
}
