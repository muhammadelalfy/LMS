<?php

namespace Modules\Students\Tests\Feature;

use Modules\Students\Models\FaceTemplate;
use Modules\Students\Models\Student;
use Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FaceTemplatesTest extends TestCase
{
    use RefreshDatabase;

    /** A stand-in for a 128-value embedding (512 bytes), base64. */
    private function embedding(string $seed): string
    {
        return base64_encode(str_repeat($seed, 512));
    }

    private function body(array $overrides = []): array
    {
        return $overrides + [
            'model' => 'sface-int8-v1',
            'embeddings' => [$this->embedding('a'), $this->embedding('b')],
            'consent' => true,
        ];
    }

    public function test_staff_enrol_list_replace_and_remove_a_face(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($teacher);
        $student = Student::factory()->create();

        $this->putJson("/api/students/{$student->id}/face-template", $this->body())
            ->assertOk()
            ->assertJsonPath('student_id', $student->id)
            ->assertJsonPath('student_name', $student->name)
            ->assertJsonPath('model', 'sface-int8-v1')
            ->assertJsonCount(2, 'embeddings')
            ->assertJsonPath('embeddings.0', $this->embedding('a'));
        $this->assertSame($teacher->id, FaceTemplate::query()->value('consent_by'));

        $this->getJson('/api/face-templates')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student_id', $student->id)
            ->assertJsonPath('data.0.embeddings.1', $this->embedding('b'));

        // Enrolling again replaces the face; it never adds a second row.
        $this->putJson("/api/students/{$student->id}/face-template", $this->body(['embeddings' => [$this->embedding('c')]]))
            ->assertOk()->assertJsonCount(1, 'embeddings');
        $this->assertSame(1, FaceTemplate::query()->count());

        $this->deleteJson("/api/students/{$student->id}/face-template")->assertNoContent();
        $this->getJson('/api/face-templates')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_face_is_only_saved_with_parent_consent_and_valid_data(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));
        $student = Student::factory()->create();
        $url = "/api/students/{$student->id}/face-template";

        $this->putJson($url, $this->body(['consent' => false]))
            ->assertStatus(422)->assertJsonValidationErrors('consent')
            ->assertJsonPath('errors.consent.0', 'يجب تأكيد موافقة ولي الأمر قبل حفظ بصمة الوجه.');
        $this->putJson($url, ['model' => 'm', 'embeddings' => [$this->embedding('a')]])
            ->assertStatus(422)->assertJsonValidationErrors('consent');
        $this->putJson($url, $this->body(['embeddings' => []]))->assertStatus(422)->assertJsonValidationErrors('embeddings');
        $this->putJson($url, $this->body(['embeddings' => array_fill(0, 11, $this->embedding('a'))]))
            ->assertStatus(422)->assertJsonValidationErrors('embeddings');
        $this->putJson($url, $this->body(['embeddings' => [str_repeat('A', 4097)]]))
            ->assertStatus(422)->assertJsonValidationErrors('embeddings.0');
        $this->putJson($url, $this->body(['model' => '']))->assertStatus(422)->assertJsonValidationErrors('model');

        $this->assertSame(0, FaceTemplate::query()->count());
    }

    public function test_only_staff_can_read_or_change_faces(): void
    {
        $student = Student::factory()->create();
        FaceTemplate::create([
            'student_id' => $student->id, 'model' => 'm', 'embeddings' => [$this->embedding('a')], 'enrolled_at' => now(),
        ]);

        foreach (['parent', 'student'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));
            $this->getJson('/api/face-templates')->assertForbidden();
            $this->putJson("/api/students/{$student->id}/face-template", $this->body())->assertForbidden();
            $this->deleteJson("/api/students/{$student->id}/face-template")->assertForbidden();
        }
        $this->assertSame(1, FaceTemplate::query()->count());
    }

    public function test_deleting_a_student_deletes_their_face(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $student = Student::factory()->create();
        FaceTemplate::create([
            'student_id' => $student->id, 'model' => 'm', 'embeddings' => [$this->embedding('a')], 'enrolled_at' => now(),
        ]);

        $this->deleteJson("/api/students/{$student->id}")->assertNoContent();

        $this->assertSame(0, FaceTemplate::query()->count());
    }
}
