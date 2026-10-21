<?php

namespace Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Groups\Models\ClassGroup;
use Modules\Students\Models\Student;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a trial account (one with an expiry date) inside its limits: a few
 * groups and students, and no exams, payments or messages.
 */
class TrialLimits
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');
        if ($user === null || $user->expires_at === null || $request->isMethodSafe()) {
            return $next($request);
        }

        $path = trim((string) preg_replace('#^api/#', '', $request->path()), '/');
        foreach ((array) config('trial.blocked_writes') as $blocked) {
            if ($path === $blocked || str_starts_with($path, $blocked.'/')) {
                return $this->refuse('هذه الميزة غير متاحة في الحساب التجريبي.');
            }
        }

        if ($request->isMethod('POST') && $path === 'groups') {
            $own = ClassGroup::query()->where('teacher_id', $user->id)->count();
            if ($own >= (int) config('trial.max_groups')) {
                return $this->refuse('بلغ الحساب التجريبي الحد الأقصى من المجموعات ('.config('trial.max_groups').').');
            }
        }

        if ($request->isMethod('POST') && $path === 'students') {
            $names = ClassGroup::query()->where('teacher_id', $user->id)->pluck('name');
            if (! $names->contains((string) $request->input('group'))) {
                return $this->refuse('يمكن للحساب التجريبي إضافة طلاب إلى مجموعاته فقط.');
            }
            if (Student::query()->whereIn('group', $names)->count() >= (int) config('trial.max_students')) {
                return $this->refuse('بلغ الحساب التجريبي الحد الأقصى من الطلاب ('.config('trial.max_students').').');
            }
        }

        return $next($request);
    }

    private function refuse(string $message): Response
    {
        return response()->json(['message' => $message], 403);
    }
}
