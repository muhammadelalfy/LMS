<?php

declare(strict_types=1);

namespace Modules\Courses\Enums;

enum CourseKind: string
{
    case Course = 'course';
    case Track = 'track';
    case Initiative = 'initiative';
    case IntensiveProgram = 'intensive_program';
    case SeasonalProgram = 'seasonal_program';
}
