<?php

namespace Modules\Core\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Lets one request at a time run a job that is safe but wasteful to repeat,
 * such as the absence sweep that every open staff phone triggers on the same
 * five-minute beat. The others return at once; the one that holds the lock
 * has already done the work for all of them.
 */
final class Singleflight
{
    /**
     * @template T
     *
     * @param  callable(): T  $work
     * @param  T  $busy  what to return when someone else is already running it
     * @return T
     */
    public static function run(string $key, int $maxSeconds, callable $work, mixed $busy): mixed
    {
        $lock = Cache::lock('singleflight:'.$key, $maxSeconds);
        if (! $lock->get()) {
            return $busy;
        }

        try {
            return $work();
        } finally {
            $lock->release();
        }
    }
}
