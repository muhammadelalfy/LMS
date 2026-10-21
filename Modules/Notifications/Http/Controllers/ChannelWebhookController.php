<?php

namespace Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Notifications\Models\ContactChannel;
use Modules\Notifications\Models\NotificationDelivery;
use Illuminate\Http\Request;

/**
 * What WhatsApp and the SMS gateway tell us afterwards: delivered, read,
 * failed, and replies such as STOP. A report only counts when its signature
 * (WhatsApp) or shared secret (SMS) is right; either one answers 404 when the
 * channel is not configured, 403 when the proof is wrong.
 */
class ChannelWebhookController extends Controller
{
    /** Words that mean "stop messaging me", in Arabic and English. */
    private const STOP_WORDS = ['stop', 'unsubscribe', 'ايقاف', 'إيقاف', 'الغاء', 'إلغاء', 'توقف'];

    /** Order of states: a late "sent" report never undoes "read". */
    private const RANK = ['queued' => 0, 'sending' => 1, 'sent' => 2, 'delivered' => 3, 'read' => 4];

    /** Meta's one-time check that the URL is ours. */
    public function whatsappVerify(Request $request)
    {
        $token = config('services.whatsapp.verify_token');
        abort_unless($token, 404);
        abort_unless(
            $request->query('hub_mode') === 'subscribe' && hash_equals($token, (string) $request->query('hub_verify_token')),
            403,
        );

        return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
    }

    public function whatsapp(Request $request)
    {
        $secret = config('services.whatsapp.app_secret');
        abort_unless($secret, 404);
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless(hash_equals($expected, (string) $request->header('X-Hub-Signature-256')), 403);

        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                foreach ($value['statuses'] ?? [] as $status) {
                    $this->advance((string) ($status['id'] ?? ''), (string) ($status['status'] ?? ''), $status['errors'][0]['code'] ?? null);
                }
                foreach ($value['messages'] ?? [] as $message) {
                    $this->maybeStop('whatsapp', '+'.ltrim((string) ($message['from'] ?? ''), '+'), (string) ($message['text']['body'] ?? ''));
                }
            }
        }

        return response('', 200);
    }

    /** Body: {"id": "...", "status": "delivered|failed"} or {"from": "+20…", "message": "STOP"}. */
    public function sms(Request $request)
    {
        $secret = config('services.sms.webhook_secret');
        abort_unless($secret, 404);
        abort_unless(hash_equals($secret, (string) $request->header('X-Webhook-Secret')), 403);

        if ($request->filled('id')) {
            $this->advance((string) $request->input('id'), (string) $request->input('status'));
        }
        if ($request->filled('from')) {
            $this->maybeStop('sms', (string) $request->input('from'), (string) $request->input('message'));
        }

        return response('', 200);
    }

    private function advance(string $providerId, string $status, mixed $error = null): void
    {
        $delivery = $providerId === '' ? null : NotificationDelivery::query()->where('provider_id', $providerId)->first();
        if ($delivery === null) {
            return;
        }
        if ($status === 'failed') {
            $delivery->update(['state' => 'failed', 'reason' => $error === null ? 'provider_failed' : 'provider_'.$error]);

            return;
        }
        $current = self::RANK[$delivery->state] ?? -1;
        if (! isset(self::RANK[$status]) || self::RANK[$status] <= $current) {
            return;
        }
        $delivery->update(['state' => $status, $status.'_at' => now()]);
    }

    private function maybeStop(string $channel, string $from, string $text): void
    {
        if (! in_array(mb_strtolower(trim($text)), self::STOP_WORDS, true)) {
            return;
        }
        ContactChannel::query()
            ->where(['channel' => $channel, 'address' => $from])
            ->update(['opted_out_at' => now(), 'source' => 'stop-reply']);
    }
}
