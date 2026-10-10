<?php

namespace Modules\Learning\Http\Controllers;

use Modules\Core\Http\Concerns\AuthorizesStaff;
use App\Http\Controllers\Controller;
use Modules\Learning\Services\Ocr\HandwritingReader;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Reads the text in a photo of handwriting, for staff writing homework. */
class HandwritingController extends Controller
{
    use AuthorizesStaff;

    /** Largest accepted photo, as base64 (about 6 MB of image). */
    private const MAX_BASE64_LENGTH = 8_000_000;

    public function __invoke(Request $request, HandwritingReader $reader)
    {
        $this->authorizeStaff($request);
        $data = $request->validate([
            'image' => ['required', 'string', 'max:'.self::MAX_BASE64_LENGTH],
            'media_type' => ['nullable', Rule::in(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])],
        ]);
        if (base64_decode($data['image'], true) === false) {
            throw ValidationException::withMessages(['image' => 'الصورة غير صالحة.']);
        }
        if (! $reader->isConfigured()) {
            throw ValidationException::withMessages(['image' => 'قراءة الخط اليدوي غير مفعّلة بعد. اطلب من الإدارة ضبط مفتاح الخدمة.']);
        }

        return ['text' => $reader->read($data['image'], $data['media_type'] ?? 'image/jpeg')];
    }
}
