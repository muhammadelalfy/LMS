<?php

declare(strict_types=1);

namespace Modules\Courses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Courses\Enums\ContentStatus;
use Modules\Courses\Models\Concerns\HasPublicationLifecycle;

class CourseLesson extends Model
{
    use HasPublicationLifecycle;

    protected $fillable = [
        'title', 'description', 'summary', 'key_points', 'estimated_duration_minutes',
        'video_url', 'live_at', 'live_provider', 'live_link',
    ];

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'key_points' => 'array',
            'estimated_duration_minutes' => 'integer',
            'position' => 'integer',
            'live_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(CourseSubject::class, 'subject_id');
    }

    /** A lesson has nothing that must exist before it is published. */
    public function assertPublishable(): void {}
}
