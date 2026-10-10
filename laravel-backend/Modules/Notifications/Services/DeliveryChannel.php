<?php

namespace Modules\Notifications\Services;

use Modules\Notifications\Models\NotificationDelivery;

/** One way to reach a phone. A vendor is swapped by changing the class behind a channel name. */
interface DeliveryChannel
{
    public function send(string $address, NotificationDelivery $delivery): ChannelResult;
}
