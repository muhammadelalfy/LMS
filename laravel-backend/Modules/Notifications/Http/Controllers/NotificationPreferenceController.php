<?php

namespace Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Notifications\Models\ContactChannel;
use Modules\Notifications\Models\NotificationPreference;
use Modules\Notifications\Models\NotificationSetting;
use Modules\Notifications\Services\ChannelRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * What reaches a person and where: whether SMS and WhatsApp are switched on
 * (needs a verified phone), which kinds of notice use which channel, and
 * quiet hours. The in-app inbox is always on.
 */
class NotificationPreferenceController extends Controller
{
    private const CHANNELS = ['push', 'whatsapp', 'sms'];

    public function show(Request $request)
    {
        return $this->present($request->user()->id);
    }

    public function update(Request $request)
    {
        $userId = $request->user()->id;
        $categories = array_keys(config('notifications.categories'));
        $data = $request->validate([
            'channels' => 'sometimes|array',
            'channels.sms' => 'sometimes|boolean',
            'channels.whatsapp' => 'sometimes|boolean',
            'quiet_hours' => 'sometimes|nullable|array',
            'quiet_hours.from' => 'required_with:quiet_hours|date_format:H:i',
            'quiet_hours.to' => 'required_with:quiet_hours|date_format:H:i',
            'categories' => 'sometimes|array',
            'categories.*.key' => 'required|string|in:'.implode(',', $categories),
            'categories.*.push' => 'sometimes|boolean',
            'categories.*.whatsapp' => 'sometimes|boolean',
            'categories.*.sms' => 'sometimes|boolean',
        ]);

        foreach ($data['categories'] ?? [] as $row) {
            $critical = config("notifications.categories.{$row['key']}.critical");
            if ($critical && ($row['push'] ?? true) === false) {
                throw ValidationException::withMessages([
                    'categories' => 'لا يمكن إيقاف الإشعارات الفورية للتنبيهات المهمة.',
                ]);
            }
        }

        foreach ($data['channels'] ?? [] as $channel => $on) {
            $this->switchChannel($userId, $channel, (bool) $on);
        }
        foreach ($data['categories'] ?? [] as $row) {
            foreach (self::CHANNELS as $channel) {
                if (array_key_exists($channel, $row)) {
                    NotificationPreference::query()->updateOrCreate(
                        ['user_id' => $userId, 'category' => $row['key'], 'channel' => $channel],
                        ['enabled' => (bool) $row[$channel]],
                    );
                }
            }
        }
        if (array_key_exists('quiet_hours', $data)) {
            NotificationSetting::query()->updateOrCreate(['user_id' => $userId], [
                'quiet_from' => $data['quiet_hours']['from'] ?? null,
                'quiet_to' => $data['quiet_hours']['to'] ?? null,
            ]);
        }

        return $this->present($userId);
    }

    private function switchChannel(int $userId, string $channel, bool $on): void
    {
        $contact = ContactChannel::query()->where(['user_id' => $userId, 'channel' => $channel])->first();
        if ($contact === null || $contact->verified_at === null) {
            if ($on) {
                throw ValidationException::withMessages(['channels' => 'تحقق من رقم هاتفك أولاً.']);
            }

            return;
        }
        $contact->update($on
            ? ['opted_in_at' => now(), 'opted_out_at' => null, 'source' => 'app']
            : ['opted_out_at' => now(), 'source' => 'app']);
    }

    private function present(int $userId): array
    {
        $contacts = ContactChannel::query()->where('user_id', $userId)->get()->keyBy('channel');
        $phone = $contacts->first();
        $stored = NotificationPreference::query()->where('user_id', $userId)->get()
            ->mapWithKeys(fn ($row) => ["{$row->category}.{$row->channel}" => $row->enabled]);
        $quiet = NotificationSetting::query()->find($userId);

        $categories = [];
        foreach (config('notifications.categories') as $key => $category) {
            $row = ['key' => $key, 'label' => $category['label'], 'critical' => $category['critical']];
            foreach (self::CHANNELS as $channel) {
                $row[$channel] = $stored["{$key}.{$channel}"] ?? true;
            }
            $categories[] = $row;
        }

        return [
            'phone' => $phone === null ? null : [
                'masked' => PhoneController::mask($phone->address),
                'verified' => $phone->verified_at !== null,
            ],
            'channels' => collect(ChannelRegistry::PAID)->mapWithKeys(
                fn (string $channel) => [$channel => (bool) $contacts->get($channel)?->canReceive()],
            ),
            'quiet_hours' => $quiet?->quiet_from === null ? null : ['from' => $quiet->quiet_from, 'to' => $quiet->quiet_to],
            'categories' => $categories,
        ];
    }
}
