<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $status = fake()->randomElement(['paid', 'pending', 'overdue']);
        $dueAt = now()->subDays(fake()->numberBetween(0, 20));

        return [
            'student_id' => Student::factory(),
            'amount' => fake()->randomElement([350, 450, 500, 650]),
            'status' => $status,
            'due_at' => $dueAt,
            'paid_at' => $status === 'paid' ? $dueAt->copy()->subDays(fake()->numberBetween(0, 3)) : null,
            'note' => $status === 'paid' ? 'تم السداد نقداً في المركز' : 'يرجى متابعة حالة الاشتراك مع الإدارة',
            'recorded_by' => null,
        ];
    }
}
