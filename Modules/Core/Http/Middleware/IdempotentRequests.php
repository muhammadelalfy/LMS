<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a write safe to send again.
 *
 * A phone that loses its connection cannot tell whether the server saw its
 * last request, so it keeps the request and sends it again later with the
 * same `Idempotency-Key`. The first successful answer is remembered for a
 * week and returned for every repeat, so a payment or a check-in is recorded
 * once however many times it arrives.
 *
 * Keys are scoped to the signed-in person, the method and the path, and (via
 * the school's cache prefix) to the school. Failures are not remembered: a
 * request that was refused can be corrected and sent again with the same key.
 */
final class IdempotentRequests
{
    private const KEY_PATTERN = '/^[A-Za-z0-9._:-]{8,100}$/';

    private const REMEMBER_SECONDS = 7 * 24 * 3600;

    /** Largest answer kept, so a big download is never copied into the cache. */
    private const MAX_BYTES = 262_144;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        if ($key === null || ! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }
        if (! preg_match(self::KEY_PATTERN, $key)) {
            return response()->json(['message' => 'Idempotency-Key غير صالح.'], 400);
        }
        // Not signed in: let the route's own authentication answer.
        $user = $request->user('sanctum');
        if ($user === null) {
            return $next($request);
        }

        $cacheKey = 'idempotency:'.$user->getAuthIdentifier().':'.sha1($request->method().' '.$request->path().' '.$key);
        if (is_array($stored = Cache::get($cacheKey))) {
            return $this->replay($stored);
        }

        $lock = Cache::lock($cacheKey.':lock', 30);
        try {
            // The same request arriving twice at once: the second waits for the first.
            $lock->block(10);
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'الطلب قيد المعالجة. أعد المحاولة بعد لحظات.'], 409);
        }

        try {
            if (is_array($stored = Cache::get($cacheKey))) {
                return $this->replay($stored);
            }

            $response = $next($request);
            $content = $response->getContent();
            if ($response->isSuccessful() && is_string($content) && strlen($content) <= self::MAX_BYTES) {
                Cache::put($cacheKey, [
                    'status' => $response->getStatusCode(),
                    'content' => $content,
                    'type' => $response->headers->get('Content-Type', 'application/json'),
                ], self::REMEMBER_SECONDS);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    /** @param  array{status: int, content: string, type: string}  $stored */
    private function replay(array $stored): Response
    {
        return response($stored['content'], $stored['status'], [
            'Content-Type' => $stored['type'],
            'Idempotency-Replayed' => 'true',
        ]);
    }
}
