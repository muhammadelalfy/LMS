<?php

namespace Modules\Notifications\Models;

use Modules\Groups\Models\ClassGroup;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/** A person's quiet hours: paid, non-critical notices wait until they end. */
class NotificationSetting extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = ['user_id', 'quiet_from', 'quiet_to'];

    /** When the quiet window now in force ends, or null outside quiet hours. */
    public static function quietEnd(int $userId, CarbonInterface $now): ?CarbonInterface
    {
        $setting = self::query()->find($userId);
        if ($setting === null || $setting->quiet_from === null || $setting->quiet_to === null) {
            return null;
        }
        $minutes = $now->hour * 60 + $now->minute;
        $from = ClassGroup::minutesOf($setting->quiet_from);
        $to = ClassGroup::minutesOf($setting->quiet_to);
        $inside = $from <= $to ? ($minutes >= $from && $minutes < $to) : ($minutes >= $from || $minutes < $to);
        if (! $inside) {
            return null;
        }
        $end = $now->setTime(intdiv($to, 60), $to % 60);

        return $end->lessThanOrEqualTo($now) ? $end->addDay() : $end;
    }
}
