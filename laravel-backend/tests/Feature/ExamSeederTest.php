<?php

namespace Tests\Feature;

use App\Models\ExamDepartment;
use App\Models\ExamQuestion;
use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\ExamSessionEvent;
use App\Models\ExamTemplate;
use Database\Seeders\ArabicDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance('env', 'testing');
    }

    public function test_arabic_exam_seed_is_complete_and_idempotent(): void
    {
        $this->seed(ArabicDemoSeeder::class);
        $firstCounts = $this->counts();

        $this->seed(ArabicDemoSeeder::class);
        $secondCounts = $this->counts();

        $this->assertSame(['departments' => 3, 'templates' => 3, 'questions' => 12, 'sessions' => 4, 'answers' => 16, 'events' => 6], $firstCounts);
        $this->assertSame($firstCounts, $secondCounts);
        $this->assertDatabaseHas('exam_templates', ['title' => 'اختبار الجبر الأول', 'status' => 'published']);
        $this->assertDatabaseHas('exam_sessions', ['status' => 'submitted']);
        $this->assertDatabaseHas('exam_session_events', ['type' => 'camera_granted']);
    }

    private function counts(): array
    {
        return [
            'departments' => ExamDepartment::count(),
            'templates' => ExamTemplate::count(),
            'questions' => ExamQuestion::count(),
            'sessions' => ExamSession::count(),
            'answers' => ExamSessionAnswer::count(),
            'events' => ExamSessionEvent::count(),
        ];
    }
}
