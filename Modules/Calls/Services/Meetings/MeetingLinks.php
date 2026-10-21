<?php

namespace Modules\Calls\Services\Meetings;

use Modules\Groups\Models\ClassGroup;
use Modules\Calls\Models\ProviderAccount;
use Modules\Auth\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Creates a Google Meet or Zoom meeting in the teacher's own account and returns its join link. */
class MeetingLinks
{
    public function __construct(private readonly OAuthProvider $oauth)
    {
    }

    /** Whether [$link] is really a link of [$provider], so a typo or another site is refused. */
    public static function isLinkOf(string $provider, string $link): bool
    {
        $parts = parse_url($link);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }
        $host = strtolower($parts['host']);

        return match ($provider) {
            'meet' => $host === 'meet.google.com',
            'zoom' => $host === 'zoom.us' || str_ends_with($host, '.zoom.us'),
            default => false,
        };
    }

    public function create(User $user, string $provider, ClassGroup $group, CarbonInterface $day): string
    {
        $account = ProviderAccount::query()->where(['user_id' => $user->id, 'provider' => $provider === 'meet' ? 'google' : 'zoom'])->first();
        if ($account === null) {
            throw new HttpException(422, 'اربط حسابك أولاً، أو الصق رابط الاجتماع.');
        }
        $token = $this->oauth->freshToken($account);
        $start = $day->setTimeFromTimeString($group->start_time);
        $end = $day->setTimeFromTimeString($group->end_time);

        try {
            return $provider === 'meet'
                ? $this->meet($token, $group, $start, $end)
                : $this->zoom($token, $group, $start, $end);
        } catch (RequestException) {
            throw new HttpException(422, 'تعذر إنشاء الاجتماع الآن. الصق رابطاً بدلاً من ذلك.');
        }
    }

    private function meet(string $token, ClassGroup $group, CarbonInterface $start, CarbonInterface $end): string
    {
        $event = Http::withToken($token)->timeout(10)
            ->post('https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1', [
                'summary' => "حصة {$group->name}",
                'start' => ['dateTime' => $start->toRfc3339String(), 'timeZone' => config('app.timezone')],
                'end' => ['dateTime' => $end->toRfc3339String(), 'timeZone' => config('app.timezone')],
                'conferenceData' => ['createRequest' => [
                    'requestId' => (string) Str::uuid(), 'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ]],
            ])->throw()->json();

        return $this->required($event['hangoutLink'] ?? null);
    }

    private function zoom(string $token, ClassGroup $group, CarbonInterface $start, CarbonInterface $end): string
    {
        $meeting = Http::withToken($token)->timeout(10)->post('https://api.zoom.us/v2/users/me/meetings', [
            'topic' => "حصة {$group->name}", 'type' => 2,
            'start_time' => $start->format('Y-m-d\TH:i:s'), 'timezone' => config('app.timezone'),
            'duration' => max(15, (int) $start->diffInMinutes($end, true)),
        ])->throw()->json();

        return $this->required($meeting['join_url'] ?? null);
    }

    private function required(?string $link): string
    {
        return $link ?: throw new HttpException(422, 'لم يُرجع المزوّد رابط اجتماع.');
    }
}
