<?php

namespace Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** One person's yes/no for a kind of notice on a channel; absent means yes. */
class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'category', 'channel', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public static function enabled(int $userId, string $category, string $channel): bool
    {
        return self::query()
            ->where(['user_id' => $userId, 'category' => $category, 'channel' => $channel])
            ->value('enabled') ?? true;
    }

    /**
     * @param  array<int, int>  $userIds
     * @return Collection<int, int>  those who turned [$channel] off for [$category]
     */
    public static function disabledUsers(string $category, string $channel, array $userIds): Collection
    {
        return self::query()
            ->where(['category' => $category, 'channel' => $channel, 'enabled' => false])
            ->whereIn('user_id', $userIds)
            ->pluck('user_id');
    }
}
