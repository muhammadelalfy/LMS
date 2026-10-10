<?php

namespace Modules\Notifications\Services;

use Modules\Notifications\Jobs\SendChannelDelivery;
use Modules\Notifications\Models\ContactChannel;
use Modules\Notifications\Models\NotificationDelivery;
use Modules\Notifications\Models\NotificationPreference;
use Illuminate\Support\Collection;

/**
 * After a notice reaches the inbox and push, queues the paid channels its
 * category falls back to (config/notifications.php), each after its wait,
 * for the people who switched that channel on. Rows are inserted in bulk and
 * the (notice, person, channel) key is unique, so scheduling twice is harmless.
 */
class FallbackScheduler
{
    private const CHUNK = 500;

    /** @param  Collection<int, \Modules\Auth\Models\User>  $users */
    public function schedule(Collection $users, string $ref, string $title, string $body, string $category): void
    {
        foreach (config("notifications.chains.{$category}", []) as [$channel, $waitMinutes]) {
            $wanted = ContactChannel::query()
                ->where('channel', $channel)->reachable()
                ->whereIn('user_id', $users->modelKeys())
                ->pluck('user_id')
                ->diff(NotificationPreference::disabledUsers($category, $channel, $users->modelKeys()));

            $sendAfter = now()->addMinutes($waitMinutes);
            foreach ($wanted->chunk(self::CHUNK) as $chunk) {
                NotificationDelivery::query()->insertOrIgnore($chunk->map(fn (int $userId) => [
                    'ref' => $ref, 'user_id' => $userId, 'channel' => $channel, 'category' => $category,
                    'title' => $title, 'body' => $body, 'state' => 'queued', 'attempts' => 0,
                    'send_after' => $sendAfter, 'created_at' => now(), 'updated_at' => now(),
                ])->all());

                NotificationDelivery::query()
                    ->where(['ref' => $ref, 'channel' => $channel])->whereIn('user_id', $chunk->all())
                    ->pluck('id')
                    ->each(fn (int $id) => SendChannelDelivery::dispatch($id, $channel)->delay($sendAfter));
            }
        }
    }
}
