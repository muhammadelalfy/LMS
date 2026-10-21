<?php

namespace Modules\Calls\Services;

/** Minimal HS256 JWT: what LiveKit uses for join tokens and webhook proofs. */
final class Jwt
{
    public static function encode(array $claims, string $secret): string
    {
        $segments = [
            self::base64Url((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT'])),
            self::base64Url((string) json_encode($claims)),
        ];
        $segments[] = self::base64Url(hash_hmac('sha256', implode('.', $segments), $secret, true));

        return implode('.', $segments);
    }

    /** The claims when the signature is right and the token has not expired, else null. */
    public static function decode(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        $expected = self::base64Url(hash_hmac('sha256', "{$parts[0]}.{$parts[1]}", $secret, true));
        if (! hash_equals($expected, $parts[2])) {
            return null;
        }
        $claims = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (! is_array($claims) || (isset($claims['exp']) && $claims['exp'] < time())) {
            return null;
        }

        return $claims;
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
