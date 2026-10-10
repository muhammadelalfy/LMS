<?php

namespace Modules\Reports\Services;

use Illuminate\Support\Facades\Cache;

/**
 * A counter that moves whenever data the school report is built from
 * changes. The report is cached under the current counter, so a change is
 * visible at once while a burst of staff opening the report costs one
 * computation. Writes that skip model events (bulk upserts) are picked up
 * when the short cache lifetime ends.
 */
final class ReportVersion
{
    private const KEY = 'reports.version';

    public static function current(): int
    {
        return (int) Cache::get(self::KEY, 1);
    }

    public static function bump(): void
    {
        Cache::add(self::KEY, 1);
        Cache::increment(self::KEY);
    }
}
