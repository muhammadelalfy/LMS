<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Auth\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAnyRole('admin') && ! $request->user()->isDemo(), 403, 'هذه الصفحة للمشرف فقط.');

        $page = AuditLog::query()->with('actor:id,name')->latest('id')->paginate(30);
        $page->getCollection()->transform(fn (AuditLog $log) => [
            'id' => $log->id,
            'action' => $log->action,
            'actor' => $log->actor?->name,
            'subject' => $log->subject_label,
            'details' => $log->details,
            'at' => $log->created_at->toIso8601String(),
        ]);

        return $page;
    }
}
