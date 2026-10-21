<?php

declare(strict_types=1);

namespace Modules\Courses\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Modules\Courses\Enums\ContentStatus;
use Modules\Courses\Enums\OutlineStyle;
use Modules\Courses\Exceptions\ContentRuleViolation;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseLesson;
use Modules\Courses\Models\CourseSubject;

/** Changes to a course's structure: each locks its parent so concurrent edits cannot interleave. */
final class CourseOutline
{
    public function addSubject(Course $course, array $data): CourseSubject
    {
        return DB::transaction(function () use ($course, $data): CourseSubject {
            $course->newQuery()->whereKey($course->getKey())->lockForUpdate()->value('id');
            $course->refresh();

            if ($course->outline_style === OutlineStyle::Lessons && $course->subjects()->count() >= 1) {
                throw ContentRuleViolation::singleSubjectOutline();
            }

            $subject = $course->subjects()->make($data);
            $subject->position = (int) $course->subjects()->max('position') + 1;
            $subject->save();

            return $subject;
        });
    }

    public function addLesson(CourseSubject $subject, array $data): CourseLesson
    {
        return DB::transaction(function () use ($subject, $data): CourseLesson {
            $subject->newQuery()->whereKey($subject->getKey())->lockForUpdate()->value('id');

            $lesson = $subject->lessons()->make($data);
            $lesson->position = (int) $subject->lessons()->max('position') + 1;
            $lesson->save();

            return $lesson;
        });
    }

    /** @param  list<int>  $orderedIds  every current child, once, in the new order */
    public function reorder(HasMany $children, array $orderedIds): void
    {
        DB::transaction(function () use ($children, $orderedIds): void {
            $parent = $children->getParent();
            $parent->newQuery()->whereKey($parent->getKey())->lockForUpdate()->value($parent->getKeyName());

            $current = $children->getQuery()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $requested = array_map('intval', $orderedIds);
            sort($requested);
            if ($current !== $requested) {
                throw ContentRuleViolation::reorderMismatch();
            }

            foreach (array_values($orderedIds) as $index => $id) {
                $children->getRelated()->newQuery()->whereKey($id)->update(['position' => $index + 1]);
            }
        });
    }

    public function changeStatus(Model $content, ContentStatus $target): Model
    {
        return DB::transaction(function () use ($content, $target): Model {
            $content->newQuery()->whereKey($content->getKey())->lockForUpdate()->value($content->getKeyName());
            $content->refresh();

            if ($content->status === $target) {
                return $content;
            }
            $content->transitionTo($target);
            $content->save();

            return $content;
        });
    }

    /** Only content that was never published may be deleted; anything else is hidden or archived instead. */
    public function delete(Model $content): void
    {
        DB::transaction(function () use ($content): void {
            $content->newQuery()->whereKey($content->getKey())->lockForUpdate()->value($content->getKeyName());
            $content->refresh();
            $content->assertNeverPublished();
            $content->delete();
        });
    }
}
