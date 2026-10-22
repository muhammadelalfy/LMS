<?php

declare(strict_types=1);

namespace Modules\Courses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Courses\Enums\ContentStatus;
use Modules\Courses\Exceptions\ContentRuleViolation;
use Modules\Courses\Models\Concerns\HasPublicationLifecycle;

class CourseSubject extends Model
{
    use HasPublicationLifecycle;

    protected $fillable = ['title', 'description'];

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return ['status' => ContentStatus::class, 'position' => 'integer', 'published_at' => 'datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class, 'subject_id')->orderBy('position')->orderBy('id');
    }

    public function assertPublishable(): void
    {
        if (! $this->lessons()->where('status', ContentStatus::Published)->exists()) {
            throw ContentRuleViolation::subjectWithoutPublishedLessons();
        }
    }
}
