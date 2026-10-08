<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * A group's weekly timetable. `days` holds ISO weekdays (1 = Monday …
 * 7 = Sunday); times are wall-clock "HH:MM" in the academy's local time.
 */
class ClassGroup extends Model
{
    /** How long before its start a group's students may already check in. */
    public const EARLY_ARRIVAL_MINUTES = 30;

    protected $fillable = ['name', 'start_time', 'end_time', 'days', 'late_after_minutes'];

    protected function casts(): array
    {
        return ['days' => 'array', 'late_after_minutes' => 'integer'];
    }

    public function meetsOn(CarbonInterface $day): bool
    {
        return in_array($day->dayOfWeekIso, array_map('intval', $this->days ?? []), true);
    }

    /** Minutes since midnight of an "HH:MM" time. */
    public static function minutesOf(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time) + [0, 0]);

        return $hours * 60 + $minutes;
    }

    /** Present until the grace period after the start, late afterwards. */
    public function statusAt(CarbonInterface $localTime): string
    {
        if (! $this->meetsOn($localTime)) {
            return 'present';
        }
        $now = $localTime->hour * 60 + $localTime->minute;

        return $now > self::minutesOf($this->start_time) + $this->late_after_minutes ? 'late' : 'present';
    }

    /**
     * Whether a student of this group is expected at the door at [$localTime]:
     * on a lesson day, from [EARLY_ARRIVAL_MINUTES] before the start until the
     * end. Any other arrival is "not your group's time" and needs confirming.
     */
    public function acceptsArrivalAt(CarbonInterface $localTime): bool
    {
        if (! $this->meetsOn($localTime)) {
            return false;
        }
        $now = $localTime->hour * 60 + $localTime->minute;

        return $now >= self::minutesOf($this->start_time) - self::EARLY_ARRIVAL_MINUTES
            && $now < self::minutesOf($this->end_time);
    }

    public function hasEndedAt(CarbonInterface $localTime): bool
    {
        return $this->meetsOn($localTime)
            && $localTime->hour * 60 + $localTime->minute >= self::minutesOf($this->end_time);
    }
}
