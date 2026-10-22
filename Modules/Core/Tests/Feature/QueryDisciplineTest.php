<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Modules\Auth\Models\User;
use Modules\Core\Support\QueryLoopGuard;
use Modules\Students\Models\Student;
use Tests\TestCase;

/** N+1 and queries inside a loop fail loudly while developing and testing. */
class QueryDisciplineTest extends TestCase
{
    use RefreshDatabase;

    public function test_reading_a_relation_that_was_not_loaded_is_an_error(): void
    {
        Student::factory()->count(2)->create();

        $this->expectException(LazyLoadingViolationException::class);

        Student::query()->get()->each(fn (Student $student) => $student->account);
    }

    public function test_a_relation_loaded_up_front_is_fine(): void
    {
        Student::factory()->count(2)->create();

        $students = Student::query()->with('account')->get();

        $this->assertCount(2, $students->map(fn (Student $student) => $student->account));
    }

    public function test_the_same_query_repeated_while_one_request_is_answered_is_an_error(): void
    {
        $user = User::factory()->create();
        $guard = app(QueryLoopGuard::class);

        $this->expectException(LogicException::class);

        $guard->around(function () use ($user): void {
            for ($i = 0; $i <= QueryLoopGuard::LIMIT; $i++) {
                User::query()->find($user->id);
            }
        });
    }

    public function test_a_different_query_each_time_or_a_few_repeats_is_fine(): void
    {
        $user = User::factory()->create();

        app(QueryLoopGuard::class)->around(function () use ($user): void {
            for ($i = 0; $i < QueryLoopGuard::LIMIT; $i++) {
                User::query()->find($user->id);
            }
        });

        $this->assertTrue(true);
    }
}
