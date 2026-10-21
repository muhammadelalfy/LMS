<?php

namespace Modules\Calls\Services;

use Illuminate\Support\Str;

/**
 * Names of LiveKit rooms. All schools share one LiveKit server, so the school
 * is part of the name ("alnour-call-01j…"): it keeps two schools' rooms apart,
 * and lets a webhook, which arrives on a central address, tell which school
 * the room belongs to.
 */
final class RoomName
{
    private const MARK = '-call-';

    /** A new room for the current school. */
    public static function make(): string
    {
        return tenant('id').self::MARK.Str::lower((string) Str::ulid());
    }

    /** The school a room belongs to, or null when the name is not ours. */
    public static function schoolOf(string $room): ?string
    {
        return str_contains($room, self::MARK) ? Str::beforeLast($room, self::MARK) : null;
    }
}
