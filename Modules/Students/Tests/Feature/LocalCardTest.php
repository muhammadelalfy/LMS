<?php

namespace Modules\Students\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\User;
use Modules\Students\Models\Student;
use Tests\TestCase;

/** A staff phone makes a student's card itself, so it works before and without the server. */
class LocalCardTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $extra = []): array
    {
        return array_merge([
            'name' => 'منى علي',
            'group' => 'أ',
            'grade' => 'الأول',
            'phone' => '01011112222',
        ], $extra);
    }

    public function test_a_phone_may_bring_the_cards_secret_when_it_adds_a_student(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));
        $token = Str::random(64);

        $id = $this->postJson('/api/students', $this->payload(['qr_token' => $token]))
            ->assertCreated()
            ->assertJsonMissingPath('qr_token')
            ->json('id');

        $this->getJson("/api/students/{$id}/qr")->assertOk()->assertJsonPath('payload', $token);
    }

    public function test_the_card_made_on_the_phone_checks_the_student_in(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));
        $token = Str::random(64);
        $id = $this->postJson('/api/students', $this->payload(['qr_token' => $token]))->json('id');

        $this->postJson('/api/attendance/scan', ['payload' => $token])
            ->assertSuccessful()
            ->assertJsonPath('attendance.student_id', $id);
    }

    public function test_without_a_secret_the_server_still_makes_one(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));
        $id = $this->postJson('/api/students', $this->payload())->json('id');

        $payload = $this->getJson("/api/students/{$id}/qr")->assertOk()->json('payload');

        $this->assertSame(64, strlen($payload));
    }

    public function test_a_secret_that_is_not_shaped_like_a_card_is_refused(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));

        $this->postJson('/api/students', $this->payload(['qr_token' => 'short']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('qr_token');
        $this->postJson('/api/students', $this->payload(['qr_token' => str_repeat('-', 64)]))
            ->assertUnprocessable();
    }

    public function test_a_secret_already_on_another_students_card_is_refused(): void
    {
        $other = Student::factory()->create();
        $taken = $other->ensureQrToken();
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));

        $this->postJson('/api/students', $this->payload(['qr_token' => $taken]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('qr_token');
    }

    public function test_a_new_card_can_be_issued_from_the_phone(): void
    {
        $student = Student::factory()->create();
        $old = $student->ensureQrToken();
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));
        $fresh = Str::random(64);

        $this->postJson("/api/students/{$student->id}/qr/regenerate", ['payload' => $fresh])
            ->assertOk()
            ->assertJsonPath('payload', $fresh);

        $this->assertSame($fresh, $student->fresh()->qr_token);
        $this->postJson('/api/attendance/scan', ['payload' => $old])->assertStatus(422);
    }

    public function test_regenerating_without_a_secret_still_rotates_it(): void
    {
        $student = Student::factory()->create();
        $old = $student->ensureQrToken();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $new = $this->postJson("/api/students/{$student->id}/qr/regenerate")->assertOk()->json('payload');

        $this->assertNotSame($old, $new);
        $this->assertSame(64, strlen($new));
    }
}
