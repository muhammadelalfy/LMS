<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Support\QueryLoopGuard;
use Symfony\Component\HttpFoundation\Response;

class GuardAgainstQueryLoops
{
    public function __construct(private readonly QueryLoopGuard $guard) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $this->guard->around(fn () => $next($request));
    }
}
