<?php

namespace Modules\Students\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An enrolled face: base64 little-endian float32 embeddings from the app's
 * recogniser, kept with the staff member who recorded the parent's consent.
 * Sensitive personal data — never photos, and staff-only to read.
 */
class FaceTemplate extends Model
{
    /** Most embeddings (angles) kept per student. */
    public const MAX_EMBEDDINGS = 10;

    /** Longest accepted base64 embedding; a 128-value one is 684 characters. */
    public const MAX_EMBEDDING_LENGTH = 4096;

    protected $fillable = ['student_id', 'model', 'embeddings', 'consent_by', 'enrolled_at'];

    protected function casts(): array
    {
        return ['embeddings' => 'array', 'enrolled_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** The shape the app reads, for the list and for a saved template. */
    public function toPayload(): array
    {
        return [
            'student_id' => $this->student_id,
            'student_name' => $this->student->name,
            'model' => $this->model,
            'embeddings' => $this->embeddings,
            'enrolled_at' => $this->enrolled_at->toIso8601String(),
        ];
    }
}
