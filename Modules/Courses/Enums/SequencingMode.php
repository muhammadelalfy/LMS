<?php

declare(strict_types=1);

namespace Modules\Courses\Enums;

/** How a student moves through the lessons: freely, or one after the one they finished. */
enum SequencingMode: string
{
    case Open = 'open';
    case AfterPreviousCompleted = 'after_previous_completed';
}
