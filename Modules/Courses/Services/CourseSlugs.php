<?php

declare(strict_types=1);

namespace Modules\Courses\Services;

use Illuminate\Support\Str;
use Modules\Courses\Models\Course;

final class CourseSlugs
{
    private const MAX_BASE_LENGTH = 200;

    /** A readable, unique address for a course; Arabic titles keep their letters. */
    public function uniqueFrom(string $title): string
    {
        $base = rtrim(Str::limit(Str::slug($title, '-', null), self::MAX_BASE_LENGTH, ''), '-');
        if ($base === '') {
            $base = 'course';
        }

        $taken = Course::query()->where('slug', $base)->orWhere('slug', 'like', $base.'-%')->pluck('slug')->all();
        if (! in_array($base, $taken, true)) {
            return $base;
        }
        for ($suffix = 2; in_array("{$base}-{$suffix}", $taken, true); $suffix++);

        return "{$base}-{$suffix}";
    }
}
