<?php

namespace Modules\Calls\Http\Controllers;

use Modules\Core\Http\Concerns\AuthorizesStaff;
use App\Http\Controllers\Controller;
use Modules\Groups\Models\ClassGroup;
use Modules\Calls\Models\OnlineSession;
use Modules\Chat\Services\ChatAccess;
use Modules\Calls\Services\Meetings\MeetingLinks;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * How a group meets online on a given day: in the app (built-in calls), or by
 * a Google Meet or Zoom link. Staff set it; the group's students read it to
 * show a Join button.
 */
class OnlineSessionController extends Controller
{
    use AuthorizesStaff;

    public function __construct(private readonly ChatAccess $access, private readonly MeetingLinks $links)
    {
    }

    public function show(Request $request, ClassGroup $group)
    {
        abort_unless($this->access->canAccessGroup($request->user(), $group), 403, 'لا تملك صلاحية هذه المجموعة.');
        $day = $request->validate(['day' => 'nullable|date_format:Y-m-d'])['day'] ?? now()->toDateString();

        return ['session' => OnlineSession::query()->where(['group_id' => $group->id, 'day' => $day])->first()?->present()];
    }

    public function update(Request $request, ClassGroup $group)
    {
        $this->authorizeStaff($request);
        abort_unless($this->access->canAccessGroup($request->user(), $group), 403, 'لا تملك صلاحية هذه المجموعة.');
        $data = $request->validate([
            'day' => 'nullable|date_format:Y-m-d',
            'provider' => 'required|in:builtin,meet,zoom',
            'link' => 'nullable|string|max:500',
            'create' => 'sometimes|boolean',
        ], ['provider.in' => 'اختر التطبيق، أو Meet، أو Zoom.']);
        $day = $data['day'] ?? now()->toDateString();

        $link = null;
        if ($data['provider'] !== 'builtin') {
            if (! empty($data['create'])) {
                $link = $this->links->create($request->user(), $data['provider'], $group, CarbonImmutable::parse($day));
            } else {
                $link = trim((string) ($data['link'] ?? ''));
                if (! MeetingLinks::isLinkOf($data['provider'], $link)) {
                    throw ValidationException::withMessages([
                        'link' => $data['provider'] === 'meet' ? 'أدخل رابط اجتماع Google Meet صحيحاً.' : 'أدخل رابط اجتماع Zoom صحيحاً.',
                    ]);
                }
            }
        }

        $session = OnlineSession::query()->updateOrCreate(
            ['group_id' => $group->id, 'day' => $day],
            ['provider' => $data['provider'], 'link' => $link, 'created_by' => $request->user()->id],
        );

        return ['session' => $session->present()];
    }

    public function destroy(Request $request, ClassGroup $group)
    {
        $this->authorizeStaff($request);
        abort_unless($this->access->canAccessGroup($request->user(), $group), 403);
        $day = $request->validate(['day' => 'nullable|date_format:Y-m-d'])['day'] ?? now()->toDateString();
        OnlineSession::query()->where(['group_id' => $group->id, 'day' => $day])->delete();

        return response()->noContent();
    }
}
