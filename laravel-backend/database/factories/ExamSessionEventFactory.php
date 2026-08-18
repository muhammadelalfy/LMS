<?php

namespace Database\Factories;

use App\Models\ExamSession;
use App\Models\ExamSessionEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExamSessionEvent> */
class ExamSessionEventFactory extends Factory
{
    protected $model = ExamSessionEvent::class;

    public function definition(): array
    {
        return ['session_id' => ExamSession::factory(), 'type' => 'heartbeat', 'metadata' => ['source' => 'browser', 'locale' => 'ar-EG'], 'occurred_at' => now()->subMinute()];
    }

    public function focusLoss(): static { return $this->state(fn () => ['type' => 'focus_lost', 'metadata' => ['reason' => 'window_blur']]); }
    public function cameraGranted(): static { return $this->state(fn () => ['type' => 'camera_granted', 'metadata' => ['device' => 'demo-camera']]); }
}
