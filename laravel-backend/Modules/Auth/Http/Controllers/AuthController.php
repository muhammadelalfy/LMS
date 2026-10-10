<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Auth\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', Password::defaults(), 'confirmed'],
            'role' => 'sometimes|in:parent,student',
        ]);

        $user = User::create($data);

        return response()->json($this->tokenResponse($user), 201);
    }

    public function login(Request $request)
    {
        return $this->loginForRole($request, 'general');
    }

    public function loginAsRole(Request $request, string $role)
    {
        abort_unless(in_array($role, ['admin', 'teacher', 'parent', 'student'], true), 404);

        return $this->loginForRole($request, $role);
    }

    public function me(Request $request)
    {
        return $request->user()->load('studentAccount.student');
    }

    /** Updates the signed-in account's own name and email. */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
        ]);
        $user->update($data);

        return $user->fresh()->load('studentAccount.student');
    }

    /** Changes the password; every other device is signed out, this one stays. */
    public function changePassword(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', Password::defaults(), 'confirmed', 'different:current_password'],
        ]);
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'كلمة المرور الحالية غير صحيحة.']);
        }
        $user->update(['password' => $data['password']]);

        $current = $user->currentAccessToken();
        $others = $user->tokens();
        if ($current instanceof PersonalAccessToken) {
            $others->whereKeyNot($current->id);
        }
        $others->delete();

        return ['success' => true];
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return ['success' => true];
    }

    private function loginForRole(Request $request, string $role): array
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $query = User::where('email', $data['email']);

        if ($role === 'admin') {
            $query->whereIn('role', ['admin', 'teacher']);
        } elseif ($role === 'teacher') {
            $query->where('role', 'teacher');
        } elseif ($role !== 'general') {
            $query->where('role', $role);
        }

        $user = $query->first();
        abort_unless($user && Hash::check($data['password'], $user->password), 422, 'بيانات الدخول غير صحيحة لهذا النوع من الحسابات.');

        return $this->tokenResponse($user, $role);
    }

    private function tokenResponse(User $user, ?string $loginType = null): array
    {
        return [
            'user' => $user->load('studentAccount.student'),
            'token' => $user->createToken('lms-web')->plainTextToken,
            'login_type' => $loginType ?? $user->role,
        ];
    }
}
