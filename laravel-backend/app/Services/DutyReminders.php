<?php

namespace App\Services;

use App\Models\ClassGroup;
use App\Models\Duty;
use App\Models\Student;
use Carbon\CarbonInterface;

/**
 * On a duty's due day, reminds the group's students and parents what is
 * still to do. For a group that meets that day the reminder goes out from
 * [LEAD_MINUTES] before the lesson until it ends; otherwise it goes out at
 * the first sweep of the day. Each duty is reminded once, so running this
 * often is safe.
 */
class DutyReminders
{
    /** How long before the lesson starts the reminder is sent. */
    public const LEAD_MINUTES = 120;

    public function __construct(private readonly StudentNotifier $notifier)
    {
    }

    /** @return int  the number of duties reminded */
    public function send(?CarbonInterface $localTime = null): int
    {
        $localTime ??= now();
        $minutes = $localTime->hour * 60 + $localTime->minute;
        $reminded = 0;

        $due = Duty::query()
            ->whereDate('due_on', $localTime->toDateString())
            ->whereNull('done_at')
            ->whereNull('reminded_at')
            ->get()
            ->groupBy('group');

        foreach ($due as $groupName => $duties) {
            $group = ClassGroup::query()->where('name', $groupName)->first();
            if ($group?->meetsOn($localTime)) {
                $start = ClassGroup::minutesOf($group->start_time);
                if ($minutes < $start - self::LEAD_MINUTES || $minutes >= ClassGroup::minutesOf($group->end_time)) {
                    continue;
                }
            }

            $studentIds = Student::query()->where('group', $groupName)->pluck('id')->all();
            $titles = $duties->pluck('title')->map(fn ($title) => "• {$title}")->implode("\n");
            $this->notifier->notify(
                $studentIds,
                "واجبات اليوم — {$groupName}",
                "تذكير بواجبات حصة اليوم:\n{$titles}",
                'duty',
            );
            Duty::query()->whereKey($duties->modelKeys())->update(['reminded_at' => $localTime]);
            $reminded += $duties->count();
        }

        return $reminded;
    }
}
