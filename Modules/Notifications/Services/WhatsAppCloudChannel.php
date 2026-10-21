<?php

namespace Modules\Notifications\Services;

use Modules\Notifications\Models\NotificationDelivery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** WhatsApp Business Cloud API, sending an approved template. */
class WhatsAppCloudChannel implements DeliveryChannel
{
    public function send(string $address, NotificationDelivery $delivery): ChannelResult
    {
        $config = config('services.whatsapp');
        if (empty($config['token']) || empty($config['phone_number_id'])) {
            return ChannelResult::failed('not_configured');
        }
        $template = $config['templates'][$delivery->category] ?? $config['templates']['default'];

        try {
            $response = Http::withToken($config['token'])->timeout(10)
                ->post("{$config['graph_url']}/{$config['phone_number_id']}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => ltrim($address, '+'),
                    'type' => 'template',
                    'template' => [
                        'name' => $template,
                        'language' => ['code' => $config['language']],
                        'components' => [[
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $this->parameter($delivery->title)],
                                ['type' => 'text', 'text' => $this->parameter($delivery->body)],
                            ],
                        ]],
                    ],
                ]);
        } catch (ConnectionException) {
            return ChannelResult::failed('unreachable', retryable: true);
        }

        if ($response->successful()) {
            return ChannelResult::ok($response->json('messages.0.id'));
        }

        return ChannelResult::failed(
            'http_'.$response->status(),
            retryable: $response->status() === 429 || $response->serverError(),
        );
    }

    /** Template parameters may not contain line breaks, tabs or long runs of spaces. */
    private function parameter(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
