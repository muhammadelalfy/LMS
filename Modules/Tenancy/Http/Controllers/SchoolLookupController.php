<?php

declare(strict_types=1);

namespace Modules\Tenancy\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tenancy\Models\School;

/**
 * The landing page's "enter your school code" box: turns a code (the school's subdomain) into the
 * address of its sign-in page. Public, on the central addresses only, and rate limited.
 *
 * It answers only with the school's name and that address, which anyone can already reach by
 * typing the subdomain; anything that is not a plausible code is "not found", never an error.
 */
class SchoolLookupController
{
    public function __invoke(Request $request): JsonResponse
    {
        $code = strtolower(trim((string) $request->query('code')));
        $school = preg_match('/^[a-z0-9][a-z0-9-]{0,40}$/', $code) === 1 ? School::query()->find($code) : null;
        $domain = $school?->domains()->orderBy('id')->value('domain');

        if (! $school || ! $domain) {
            return response()->json(['found' => false], 404);
        }

        return response()->json([
            'found' => true,
            'name' => $school->name,
            'url' => $this->signInUrl($request, $domain),
        ]);
    }

    private function signInUrl(Request $request, string $domain): string
    {
        $port = $request->getPort();
        $suffix = in_array($port, [80, 443], true) ? '' : ':'.$port;

        return $request->getScheme().'://'.$domain.$suffix.'/login';
    }
}
