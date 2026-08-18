<?php

namespace Database\Factories;

use App\Models\ExamSession;
use App\Models\ExamTemplate;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExamSession> */
class ExamSessionFactory extends Factory
{
    protected $model = ExamSession::class;

    public function definition(): array
    {
        return [
            'template_id' => ExamTemplate::factory()->published(),
            'student_id' => Student::factory(),
            'started_at' => now()->subMinutes(18),
            'submitted_at' => null,
            'status' => 'active',
            'camera_required' => true,
            'fullscreen_required' => true,
            'focus_loss_count' => 0,
            'last_event_at' => now()->subMinute(),
        ];
    }

    public function ready(): static { return $this->state(fn () => ['started_at' => null, 'status' => 'ready', 'last_event_at' => null]); }
    public function submitted(): static { return $this->state(fn () => ['submitted_at' => now()->subMinutes(2), 'status' => 'submitted']); }
    public function flagged(): static { return $this->state(fn () => ['status' => 'flagged', 'focus_loss_count' => 2]); }
}
