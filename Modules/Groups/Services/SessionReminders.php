<?php

namespace Modules\Groups\Services;

use Modules\Notifications\Services\StudentNotifier;

use Modules\Groups\Models\ClassGroup;
use Modules\Calls\Models\OnlineSession;
use Modules\Groups\Models\SessionReminder;
use Modules\Students\Models\Student;
use Modules\Students\Models\StudentAccount;
use Modules\Auth\Models\User;
use Carbon\CarbonInterface;

/**
 * Tells a group's students, and the teachers, that a session starts soon:
 * once per group per lesson day, from [LEAD_MINUTES] before the start until
 * the start. Safe to run as often as needed.
 */
class SessionReminders
{
    /** How long before the lesson starts the reminder is sent. */
    public const LEAD_MINUTES = 30;

    public function __construct(private readonly StudentNotifier $notifier)
    {
    }

    /** @return int  the number of groups reminded */
    public function send(?CarbonInterface $localTime = null): int
    {
        $localTime ??= now();
        $minutes = $localTime->hour * 60 + $localTime->minute;
        $reminded = 0;

        foreach (ClassGroup::query()->get() as $group) {
            if (! $group->meetsOn($localTime)) {
                continue;
            }
            $start = ClassGroup::minutesOf($group->start_time);
            if ($minutes < $start - self::LEAD_MINUTES || $minutes >= $start) {
                continue;
            }
            $marker = SessionReminder::query()->firstOrCreate(
                ['class_group_id' => $group->id, 'day' => $localTime->toDateString()],
            );
            if (! $marker->wasRecentlyCreated) {
                continue;
            }

            $this->remind($group, $start - $minutes, $localTime->toDateString());
            $reminded++;
        }

        return $reminded;
    }

    private function remind(ClassGroup $group, int $minutesLeft, string $day): void
    {
        $time = self::clockLabel($group->start_time);
        $when = "بعد {$minutesLeft} دقيقة";

        $online = $this->onlineNote($group, $day);

        $students = Student::query()->where('group', $group->name)->get();
        $this->notifier->notify(
            $students->modelKeys(),
            "حصتك {$when}",
            "تبدأ حصة {$group->name} اليوم الساعة {$time}.{$online}",
            'session',
            ['student'],
        );
        $this->notifier->notifyUsers(
            User::query()->whereIn('role', ['admin', 'teacher'])->get(),
            "حصة {$group->name} {$when}",
            "تبدأ حصة {$group->name} الساعة {$time} ({$students->count()} طالب).{$online}",
            'session',
        );
    }

    /** How to join when the session is online; empty for a session at the door. */
    private function onlineNote(ClassGroup $group, string $day): string
    {
        $session = OnlineSession::query()->where(['group_id' => $group->id, 'day' => $day])->first();

        return match ($session?->provider) {
            'builtin' => "\nالحصة عبر الإنترنت: افتح التطبيق واضغط انضمام.",
            'meet' => "\nالحصة عبر Google Meet. رابط الانضمام: {$session->link}",
            'zoom' => "\nالحصة عبر Zoom. رابط الانضمام: {$session->link}",
            default => '',
        };
    }

    /** "17:05" as «5:05 م». */
    public static function clockLabel(string $time): string
    {
        $minutes = ClassGroup::minutesOf($time);
        $hour = intdiv($minutes, 60);
        $display = $hour % 12 === 0 ? 12 : $hour % 12;

        return sprintf('%d:%02d %s', $display, $minutes % 60, $hour < 12 ? 'ص' : 'م');
    }
}
