<?php

declare(strict_types=1);

namespace Modules\Courses\Enums;

/** Sections with lessons inside, or a flat list of lessons (one hidden section). */
enum OutlineStyle: string
{
    case Subjects = 'subjects';
    case Lessons = 'lessons';
}
