<?php

namespace Modules\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The platform operator's API is open only to whoever holds CENTRAL_API_TOKEN.
 * Without a token configured it does not exist (404), so a forgotten setting
 * cannot leave it open.
 */
class OperatorToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('tenancy.operator_token');
        abort_if($token === '', 404);
        abort_unless(hash_equals($token, (string) $request->bearerToken()), 401, 'رمز المشغّل غير صحيح.');

        return $next($request);
    }
}
