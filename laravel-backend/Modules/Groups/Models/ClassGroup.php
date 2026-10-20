<?php

namespace Modules\Groups\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Models\User;
use Modules\Reports\Models\Concerns\BumpsReportVersion;

/**
 * A group's weekly timetable. `days` holds ISO weekdays (1 = Monday …
 * 7 = Sunday); times are wall-clock "HH:MM" in the academy's local time.
 */
class ClassGroup extends Model
{
    use BumpsReportVersion;

    /** How long before its start a group's students may already check in. */
    public const EARLY_ARRIVAL_MINUTES = 30;

    protected $fillable = ['name', 'start_time', 'end_time', 'days', 'late_after_minutes', 'teacher_id'];

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

    /**
     * Whether [$user] may act on this group as its teacher: an unassigned
     * group (no `teacher_id` yet) is open to any teacher; an assigned one is
     * open only to the teacher it names. This is the single source of truth
     * for "does this teacher own this group" (ADR 0002) — every module that
     * needs the rule (Chat, and every Policy this ADR adds) calls this
     * instead of re-checking `teacher_id` itself.
     */
    public function isOpenTo(User $user): bool
    {
        return $this->teacher_id === null || $this->teacher_id === $user->id;
    }

    /** Query scope: groups [$user] may act on as teacher (their own + unassigned). Callers bypass this scope entirely for an admin. */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $inner) => $inner->whereNull('teacher_id')->orWhere('teacher_id', $user->id));
    }

    /** Group names [$user] may act on, for the Student-keyed modules (Payments, Exams, Attendance, Duty, FaceTemplate). */
    public static function namesOwnedBy(User $user): array
    {
        return static::query()->ownedBy($user)->pluck('name')->all();
    }

    /**
     * The complement of [namesOwnedBy]: group names a *different*, named
     * teacher runs. A Student-keyed resource (Payments, Exams, Attendance,
     * FaceTemplate, Duty) is "owned" by [$user] when its group is *not* in
     * this list — which also, correctly, covers a group string that does not
     * (yet) match any `ClassGroup` row at all, the same "open" disposition an
     * unassigned group already has (ADR 0002). Used instead of [namesOwnedBy]
     * wherever the group is a free string, not a `ClassGroup` row itself.
     */
    public static function namesClaimedByOthers(User $user): array
    {
        return static::query()->whereNotNull('teacher_id')->where('teacher_id', '!=', $user->id)->pluck('name')->all();
    }
}
