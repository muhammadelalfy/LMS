<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Auth\Services\DemoAccounts;

/** "Try it" sign-in for schools whose operator switched demo logins on. No password is ever sent or accepted. */
class DemoLoginController extends Controller
{
    /** The roles the sign-in page may offer; empty unless this school has demo logins on. */
    public static function roles(DemoAccounts $demo): array
    {
        return tenant('demo_logins') ? $demo->available() : [];
    }

    public function store(Request $request, DemoAccounts $demo)
    {
        abort_unless(tenant('demo_logins'), 404);
        $role = $request->validate(['role' => ['required', Rule::in(DemoAccounts::ROLES)]])['role'];

        $user = $demo->userFor($role);
        abort_unless($user?->isUsable(), 404);

        return [
            'user' => $user->load('studentAccount.student'),
            // A demo session ends on its own.
            'token' => $user->createToken('demo', ['*'], now()->addHours(8))->plainTextToken,
            'login_type' => $role,
        ];
    }
}
