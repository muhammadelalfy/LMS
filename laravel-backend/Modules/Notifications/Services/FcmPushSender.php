<?php

namespace Modules\Notifications\Services;

use Modules\Notifications\Models\DeviceToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends push notifications through Firebase Cloud Messaging (free, no message
 * limit) with the HTTP v1 API. Configure FIREBASE_CREDENTIALS with the path to
 * a service-account JSON key; without it, sending is a silent no-op and users
 * still get every notification in their in-app inbox.
 */
class FcmPushSender
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function isConfigured(): bool
    {
        return $this->credentials() !== null;
    }

    /**
     * @param  Collection<int, \Modules\Auth\Models\User>  $users
     * @param  array<string, string>  $data
     */
    public function send(Collection $users, string $title, string $body, array $data = []): void
    {
        $credentials = $this->credentials();
        if ($credentials === null) {
            return;
        }
        $tokens = DeviceToken::query()->whereIn('user_id', $users->modelKeys())->pluck('token');
        if ($tokens->isEmpty()) {
            return;
        }

        try {
            $accessToken = $this->accessToken($credentials);
        } catch (Throwable $error) {
            Log::warning('FCM authentication failed', ['error' => $error->getMessage()]);

            return;
        }

        $endpoint = "https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send";
        foreach ($tokens as $token) {
            $response = Http::withToken($accessToken)->post($endpoint, [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_map('strval', $data),
                    'android' => ['priority' => 'high', 'notification' => ['channel_id' => 'school_updates']],
                ],
            ]);
            // Uninstalled apps and reset devices leave dead tokens behind.
            if ($response->status() === 404 || str_contains($response->body(), 'UNREGISTERED')) {
                DeviceToken::query()->where('token', $token)->delete();
            } elseif ($response->failed()) {
                Log::warning('FCM send failed', ['status' => $response->status(), 'body' => $response->body()]);
            }
        }
    }

    /** @return array{project_id: string, client_email: string, private_key: string}|null */
    private function credentials(): ?array
    {
        $path = config('services.fcm.credentials');
        if (! $path || ! is_readable($path)) {
            return null;
        }
        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) && isset($json['project_id'], $json['client_email'], $json['private_key'])
            ? $json
            : null;
    }

    /** OAuth access token from a self-signed service-account JWT, cached. */
    private function accessToken(array $credentials): string
    {
        return Cache::remember('fcm.access_token.'.$credentials['client_email'], now()->addMinutes(50), function () use ($credentials) {
            $now = time();
            $segments = [
                $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $this->base64Url(json_encode([
                    'iss' => $credentials['client_email'],
                    'scope' => self::SCOPE,
                    'aud' => 'https://oauth2.googleapis.com/token',
                    'iat' => $now,
                    'exp' => $now + 3600,
                ])),
            ];
            openssl_sign(implode('.', $segments), $signature, $credentials['private_key'], 'sha256WithRSAEncryption');
            $segments[] = $this->base64Url($signature);

            return Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ])->throw()->json('access_token');
        });
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
