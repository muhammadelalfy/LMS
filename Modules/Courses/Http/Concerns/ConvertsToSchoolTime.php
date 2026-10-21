<?php

declare(strict_types=1);

namespace Modules\Courses\Http\Concerns;

use Illuminate\Support\Carbon;

trait ConvertsToSchoolTime
{
    /**
     * Times arrive with their own offset (a browser sends UTC), but the database holds wall-clock times in
     * the application's timezone. Converting first keeps the instant the same; otherwise a 18:00 session
     * would be read back hours earlier or later.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function inSchoolTime(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (! empty($data[$key])) {
                $data[$key] = Carbon::parse($data[$key])->setTimezone(config('app.timezone'))->toDateTimeString();
            }
        }

        return $data;
    }
}
