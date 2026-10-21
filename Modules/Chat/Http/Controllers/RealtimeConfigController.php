<?php

namespace Modules\Chat\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Tenancy\Support\SchoolChannel;

/**
 * Where the app opens its WebSocket (a Pusher-protocol server such as Reverb).
 * `enabled` is false until broadcasting is set to reverb; the app then falls
 * back to fetching new messages every few seconds.
 */
class RealtimeConfigController extends Controller
{
    public function __invoke()
    {
        $connection = config('broadcasting.connections.reverb', []);
        $options = $connection['options'] ?? [];
        $enabled = config('broadcasting.default') === 'reverb' && ! empty($connection['key']);

        return [
            'enabled' => $enabled,
            'key' => $enabled ? $connection['key'] : null,
            'host' => $options['host'] ?? null,
            'port' => isset($options['port']) ? (int) $options['port'] : null,
            'tls' => ($options['scheme'] ?? 'https') === 'https',
            'auth_endpoint' => '/api/broadcasting/auth',
            // Every channel name of this school starts with this, e.g. "school.alnour.user.5".
            'channel_prefix' => SchoolChannel::prefix(),
        ];
    }
}
