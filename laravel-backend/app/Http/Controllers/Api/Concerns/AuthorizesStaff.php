<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;

trait AuthorizesStaff
{
    private function authorizeStaff(Request $request): void
    {
        abort_unless($request->user()->isAnyRole('admin', 'teacher'), 403);
    }
}
