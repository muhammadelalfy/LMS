<?php

namespace Modules\Core\Tests\Feature;

use Modules\Attendance\Models\AttendanceRecord;
use Modules\Groups\Models\ClassGroup;
use Modules\Exams\Models\ExamQuestion;
use Modules\Exams\Models\ExamSession;
use Modules\Exams\Models\ExamSessionAnswer;
use Modules\Exams\Models\ExamTemplate;
use Modules\Students\Models\Student;
use Modules\Students\Models\StudentAccount;
use Modules\Auth\Models\User;
use Modules\Core\Support\Singleflight;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** What keeps thousands of students in the same second from overwhelming the server. */
class ScaleHardeningTest extends TestCase
{
    use RefreshDatabase;

    // The same sweep from many phones --------------------------------------------------------

    public function test_a_second_sweep_while_one_is_running_returns_at_once_without_repeating_the_work(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 17:00:00', 'Africa/Cairo'));
        ClassGroup::create(['name' => 'أ', 'start_time' => '15:00', 'end_time' => '16:30', 'days' => [1], 'late_after_minutes' => 10]);
        Student::factory()->create(['group' => 'أ']);
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));

        $lock = Cache::lock('singleflight:sweep', 60);
        $this->assertTrue($lock->get(), 'another phone is mid-sweep');
        $this->postJson('/api/attendance/sweep', [])->assertOk()->assertJsonPath('marked_absent', 0);
        $this->assertSame(0, AttendanceRecord::count(), 'the busy request did none of the work');

        $lock->release();
        $this->postJson('/api/attendance/sweep', [])->assertOk()->assertJsonPath('marked_absent', 1);
        $this->postJson('/api/attendance/sweep', [])->assertOk()->assertJsonPath('marked_absent', 0);
    }

    public function test_the_lock_is_released_even_when_the_work_fails(): void
    {
        try {
            Singleflight::run('boom', 60, fn () => throw new \RuntimeException('x'), 0);
        } catch (\RuntimeException) {
        }

        $this->assertSame('ran', Singleflight::run('boom', 60, fn () => 'ran', 'busy'));
    }

    // The school report ---------------------------------------------------------------------------

    public function test_the_school_report_is_computed_once_per_change_and_a_change_shows_at_once(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));
        Student::factory()->create(['group' => 'أ']);

        DB::enableQueryLog();
        $first = $this->getJson('/api/reports/summary')->assertOk()->json('students');
        $queries = count(DB::getQueryLog());
        $this->getJson('/api/reports/summary')->assertOk();
        $this->getJson('/api/reports/summary')->assertOk();
        $repeated = count(DB::getQueryLog()) - $queries;
        DB::disableQueryLog();

        $this->assertSame(1, $first);
        $this->assertLessThan($queries / 2, $repeated / 2, 'the repeats cost only the auth lookups, not the aggregation');

        Student::factory()->create(['group' => 'ب']);
        $this->getJson('/api/reports/summary')->assertOk()->assertJsonPath('students', 2);
    }

    // Exam answers in one request -----------------------------------------------------------------

    private function examSession(): array
    {
        $student = Student::factory()->create();
        $user = User::factory()->create(['role' => 'student']);
        StudentAccount::create(['user_id' => $user->id, 'student_id' => $student->id, 'relationship' => 'student']);
        $template = ExamTemplate::factory()->create();
        $questions = ExamQuestion::factory()->count(3)->create(['template_id' => $template->id]);
        $session = ExamSession::create(['template_id' => $template->id, 'student_id' => $student->id, 'status' => 'ready']);

        return [$user, $session, $questions];
    }

    public function test_many_answers_are_saved_in_one_request_and_saving_twice_changes_nothing(): void
    {
        [$user, $session, $questions] = $this->examSession();
        Sanctum::actingAs($user);
        $payload = ['answers' => [
            ['question_id' => $questions[0]->id, 'answer' => 'أ'],
            ['question_id' => $questions[1]->id, 'answer' => 'ب'],
            ['question_id' => $questions[0]->id, 'answer' => 'ج'], // changed again before the save
        ]];

        $this->postJson("/api/exam-sessions/{$session->id}/answers/batch", $payload)->assertOk()->assertJsonPath('saved', 2);
        $this->postJson("/api/exam-sessions/{$session->id}/answers/batch", $payload)->assertOk();

        $this->assertSame(2, ExamSessionAnswer::count());
        $this->assertSame('ج', ExamSessionAnswer::where('question_id', $questions[0]->id)->value('answer'), 'the last value sent wins');
        $this->assertSame('active', $session->fresh()->status);
    }

    public function test_a_batch_cannot_touch_another_exam_or_a_closed_one_or_too_many_at_once(): void
    {
        [$user, $session, $questions] = $this->examSession();
        [, , $foreign] = $this->examSession();
        Sanctum::actingAs($user);

        $this->postJson("/api/exam-sessions/{$session->id}/answers/batch", ['answers' => [['question_id' => $foreign[0]->id, 'answer' => 'x']]])
            ->assertStatus(422);
        $this->assertSame(0, ExamSessionAnswer::count());

        $tooMany = array_map(fn ($i) => ['question_id' => $questions[0]->id, 'answer' => (string) $i], range(1, 101));
        $this->postJson("/api/exam-sessions/{$session->id}/answers/batch", ['answers' => $tooMany])->assertStatus(422);
        $this->postJson("/api/exam-sessions/{$session->id}/answers/batch", ['answers' => []])->assertStatus(422);

        $session->update(['status' => 'submitted']);
        $this->postJson("/api/exam-sessions/{$session->id}/answers/batch", ['answers' => [['question_id' => $questions[0]->id, 'answer' => 'x']]])
            ->assertStatus(422);

        [$other] = $this->examSession();
        Sanctum::actingAs($other);
        $this->postJson("/api/exam-sessions/{$session->id}/answers/batch", ['answers' => [['question_id' => $questions[0]->id, 'answer' => 'x']]])
            ->assertForbidden();
    }
}
