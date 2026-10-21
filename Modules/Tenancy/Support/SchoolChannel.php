<?php

namespace Modules\Tenancy\Support;

/**
 * Names for realtime channels. All schools share one realtime server, so a
 * channel name carries the school: "school.alnour.user.5". Without it,
 * user 5 of one school and user 5 of another would share a channel and read
 * each other's calls and messages.
 */
final class SchoolChannel
{
    /** The channel a message for the current school is broadcast on. */
    public static function name(string $channel): string
    {
        return self::prefix().$channel;
    }

    /** "school.<id>." for the current school; the app asks for it with /realtime/config. */
    public static function prefix(): string
    {
        return 'school.'.tenant('id').'.';
    }
}
