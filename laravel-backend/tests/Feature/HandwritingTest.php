<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Ocr\HandwritingReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HandwritingTest extends TestCase
{
    use RefreshDatabase;

    private function fakeReader(bool $configured = true): object
    {
        $reader = new class($configured) implements HandwritingReader
        {
            public array $seen = [];

            public function __construct(private readonly bool $configured)
            {
            }

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function read(string $imageBase64, string $mediaType): string
            {
                $this->seen[] = [$imageBase64, $mediaType];

                return "حل صفحة ٤٢\nتمرين ١ إلى ٨";
            }
        };
        $this->app->instance(HandwritingReader::class, $reader);

        return $reader;
    }

    public function test_staff_get_the_text_of_a_photo_of_handwriting(): void
    {
        $reader = $this->fakeReader();
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));
        $image = base64_encode('not really a jpeg');

        $this->postJson('/api/ocr/handwriting', ['image' => $image])
            ->assertOk()->assertExactJson(['text' => "حل صفحة ٤٢\nتمرين ١ إلى ٨"]);
        $this->postJson('/api/ocr/handwriting', ['image' => $image, 'media_type' => 'image/png'])->assertOk();

        $this->assertSame([[$image, 'image/jpeg'], [$image, 'image/png']], $reader->seen);
    }

    public function test_only_staff_can_read_handwriting_and_the_input_is_checked(): void
    {
        $reader = $this->fakeReader();
        $image = base64_encode('x');

        Sanctum::actingAs(User::factory()->create(['role' => 'parent']));
        $this->postJson('/api/ocr/handwriting', ['image' => $image])->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));
        $this->postJson('/api/ocr/handwriting', [])->assertStatus(422)->assertJsonValidationErrors('image');
        $this->postJson('/api/ocr/handwriting', ['image' => '***not base64***'])->assertStatus(422)->assertJsonPath('errors.image.0', 'الصورة غير صالحة.');
        $this->postJson('/api/ocr/handwriting', ['image' => $image, 'media_type' => 'application/pdf'])->assertStatus(422)->assertJsonValidationErrors('media_type');
        $this->postJson('/api/ocr/handwriting', ['image' => str_repeat('A', 8_000_001)])->assertStatus(422)->assertJsonValidationErrors('image');

        $this->assertSame([], $reader->seen);
    }

    public function test_it_says_so_when_the_reader_is_not_set_up(): void
    {
        $this->fakeReader(configured: false);
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));

        $this->postJson('/api/ocr/handwriting', ['image' => base64_encode('x')])
            ->assertStatus(422)
            ->assertJsonPath('errors.image.0', 'قراءة الخط اليدوي غير مفعّلة بعد. اطلب من الإدارة ضبط مفتاح الخدمة.');
    }
}
