<?php

namespace Modules\Notifications\Services;

use Modules\Notifications\Models\NotificationDelivery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** SMS through an aggregator's HTTP API (see config/services.php). */
class SmsGatewayChannel implements DeliveryChannel
{
    public function send(string $address, NotificationDelivery $delivery): ChannelResult
    {
        $config = config('services.sms');
        if (empty($config['url']) || empty($config['token'])) {
            return ChannelResult::failed('not_configured');
        }

        try {
            $response = Http::withToken($config['token'])->timeout(10)->post($config['url'], [
                'to' => $address,
                'from' => $config['sender'],
                'message' => trim($delivery->title.': '.$delivery->body),
            ]);
        } catch (ConnectionException) {
            return ChannelResult::failed('unreachable', retryable: true);
        }

        if ($response->successful()) {
            return ChannelResult::ok((string) ($response->json('id') ?? $response->json('message_id') ?? ''));
        }

        // Throttled or the gateway is down: worth another try. A rejected
        // number or message is not.
        return ChannelResult::failed(
            'http_'.$response->status(),
            retryable: $response->status() === 429 || $response->serverError(),
        );
    }
}
