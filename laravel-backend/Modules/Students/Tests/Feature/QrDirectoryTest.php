<?php

namespace Modules\Students\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\User;
use Modules\Students\Models\Student;
use Tests\TestCase;

/** What lets a desk phone greet a student by name when it has no connection. */
class QrDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_get_every_card_with_its_owner(): void
    {
        $first = Student::factory()->create(['name' => 'سارة محمود', 'grade' => 'الصف الأول', 'group' => 'أ']);
        $second = Student::factory()->create(['name' => 'يوسف خالد']);
        $first->ensureQrToken();
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));

        $cards = $this->getJson('/api/students/qr-directory')
            ->assertOk()
            ->assertJsonCount(2, 'cards')
            ->json('cards');

        $byId = collect($cards)->keyBy('id');
        $this->assertSame('سارة محمود', $byId[$first->id]['name']);
        $this->assertSame($first->fresh()->qr_token, $byId[$first->id]['payload']);
        $this->assertSame(64, strlen($byId[$second->id]['payload']), 'a card is issued for a student without one');
    }

    public function test_it_follows_a_rotated_card(): void
    {
        $student = Student::factory()->create();
        $old = $student->ensureQrToken();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $student->regenerateQrToken();

        $payload = $this->getJson('/api/students/qr-directory')->json('cards.0.payload');
        $this->assertNotSame($old, $payload);
    }

    public function test_students_and_parents_cannot_read_it(): void
    {
        Student::factory()->create();

        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $this->getJson('/api/students/qr-directory')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'parent']));
        $this->getJson('/api/students/qr-directory')->assertForbidden();
    }
}
