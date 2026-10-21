<?php

namespace Modules\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Tenancy\Models\School;
use Symfony\Component\HttpFoundation\Response;

/** Turns away every request to a school that is suspended or whose trial ended. */
class EnsureSchoolIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $school = tenant();
        if ($school instanceof School && ($reason = $school->lockedReason()) !== null) {
            return response()->json(['message' => $reason, 'code' => 'school_locked'], 403);
        }

        return $next($request);
    }
}
