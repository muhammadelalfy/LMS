<?php

declare(strict_types=1);

namespace Modules\Courses\Enums;

enum ContentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Hidden = 'hidden';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Published => 'منشور',
            self::Hidden => 'مخفي',
            self::Archived => 'مؤرشف',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, match ($this) {
            self::Draft => [self::Published, self::Archived],
            self::Published => [self::Hidden, self::Archived],
            self::Hidden => [self::Published, self::Archived],
            self::Archived => [self::Hidden],
        }, true);
    }
}
