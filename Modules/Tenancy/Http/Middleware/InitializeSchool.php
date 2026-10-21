<?php

namespace Modules\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Tenancy\Models\School;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Symfony\Component\HttpFoundation\Response;

/**
 * Finds the school a request is for, from the address it was sent to
 * (https://alnour.example.com), and refuses the platform's own addresses.
 *
 * While developing, DEFAULT_SCHOOL lets a plain http://127.0.0.1:8000 serve
 * one school, so a phone or a test can use a single URL without subdomains.
 * It does nothing outside the local and testing environments.
 */
class InitializeSchool
{
    public function __construct(
        private readonly PreventAccessFromCentralDomains $keepCentralOut,
        private readonly InitializeTenancyByDomain $byDomain,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (($school = $this->developmentSchool($request)) !== null) {
            tenancy()->initialize($school);

            return $next($request);
        }

        return $this->keepCentralOut->handle($request, fn (Request $request) => $this->byDomain->handle($request, $next));
    }

    private function developmentSchool(Request $request): ?School
    {
        $id = config('tenancy.default_school');
        if (! $id || ! app()->environment(['local', 'testing'])) {
            return null;
        }
        if (! in_array($request->getHost(), config('tenancy.central_domains'), true)) {
            return null;
        }

        return School::query()->find($id);
    }
}
