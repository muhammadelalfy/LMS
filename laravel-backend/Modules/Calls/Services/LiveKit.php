<?php

namespace Modules\Calls\Services;

use Illuminate\Support\Facades\Http;

/**
 * The self-hosted LiveKit media server: join tokens signed with its API
 * secret, and the few admin calls the app needs. The secret never leaves
 * the server.
 */
class LiveKit
{
    public function isConfigured(): bool
    {
        return ! empty(config('services.livekit.url')) && ! empty(config('services.livekit.key')) && ! empty(config('services.livekit.secret'));
    }

    public function url(): string
    {
        return (string) config('services.livekit.url');
    }

    /** A token that lets [$identity] join [$room], for [ttl] seconds. */
    public function joinToken(string $room, string $identity, string $name, bool $canPublish = true): string
    {
        $now = time();

        return Jwt::encode([
            'iss' => config('services.livekit.key'),
            'sub' => $identity,
            'name' => $name,
            'jti' => $identity,
            'nbf' => $now,
            'exp' => $now + (int) config('services.livekit.token_ttl', 600),
            'video' => [
                'room' => $room,
                'roomJoin' => true,
                'canSubscribe' => true,
                'canPublish' => $canPublish,
                'canPublishData' => true,
            ],
        ], config('services.livekit.secret'));
    }

    /**
     * Whether a webhook really came from our LiveKit: its Authorization
     * header is a JWT signed with our secret whose `sha256` claim is the
     * body's hash.
     */
    public function verifyWebhook(string $authorization, string $body): bool
    {
        $claims = Jwt::decode($authorization, (string) config('services.livekit.secret'));

        return $claims !== null
            && ($claims['iss'] ?? null) === config('services.livekit.key')
            && hash_equals((string) ($claims['sha256'] ?? ''), base64_encode(hash('sha256', $body, true)));
    }

    /** Lets a participant speak (or not) in a room where they joined as a listener. */
    public function setCanPublish(string $room, string $identity, bool $allowed): void
    {
        $this->admin('UpdateParticipant', $room, [
            'room' => $room, 'identity' => $identity,
            'permission' => ['can_subscribe' => true, 'can_publish' => $allowed, 'can_publish_data' => true],
        ]);
    }

    public function remove(string $room, string $identity): void
    {
        $this->admin('RemoveParticipant', $room, ['room' => $room, 'identity' => $identity]);
    }

    public function closeRoom(string $room): void
    {
        $this->admin('DeleteRoom', $room, ['room' => $room]);
    }

    private function admin(string $method, string $room, array $payload): void
    {
        if (! $this->isConfigured()) {
            return;
        }
        $token = Jwt::encode([
            'iss' => config('services.livekit.key'), 'nbf' => time(), 'exp' => time() + 60,
            'video' => ['roomAdmin' => true, 'room' => $room],
        ], config('services.livekit.secret'));

        // LiveKit's RoomService is plain JSON over HTTP; ws(s):// becomes http(s)://.
        $base = preg_replace('#^ws#', 'http', $this->url());
        Http::withToken($token)->timeout(5)->post("{$base}/twirp/livekit.RoomService/{$method}", $payload);
    }
}
