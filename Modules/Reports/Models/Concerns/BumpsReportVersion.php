<?php

namespace Modules\Reports\Models\Concerns;

use Modules\Reports\Services\ReportVersion;

/** A change to this model makes the cached school report out of date. */
trait BumpsReportVersion
{
    protected static function bootBumpsReportVersion(): void
    {
        static::saved(fn () => ReportVersion::bump());
        static::deleted(fn () => ReportVersion::bump());
    }
}
