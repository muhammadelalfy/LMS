<?php

namespace Modules\Notifications\Services;

/** The paid channels by name. */
class ChannelRegistry
{
    public const PAID = ['whatsapp', 'sms'];

    public function get(string $channel): DeliveryChannel
    {
        return match ($channel) {
            'sms' => app(SmsGatewayChannel::class),
            'whatsapp' => app(WhatsAppCloudChannel::class),
        };
    }
}
