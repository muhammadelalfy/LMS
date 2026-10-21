<?php

namespace Modules\Chat\Http\Controllers;

use Modules\Chat\Events\MessageDeleted;
use App\Http\Controllers\Controller;
use Modules\Chat\Models\ChatAuditLog;
use Modules\Groups\Models\ClassGroup;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;
use Modules\Chat\Models\MessageReport;
use Modules\Auth\Models\User;
use Modules\Chat\Models\UserBlock;
use Modules\Chat\Services\ChatAccess;
use Modules\Chat\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Chat between management and teachers, and between a teacher and the
 * students they run. All access rules are in {@see ChatAccess}.
 */
class ChatController extends Controller
{
    public function __construct(private readonly ChatAccess $access, private readonly ChatService $chat)
    {
    }

    /** People the caller may start a conversation with. */
    public function contacts(Request $request)
    {
        return ['data' => $this->access->contacts($request->user())->map(fn (User $user) => [
            'id' => $user->id, 'name' => $user->name, 'role' => $user->role,
        ])->values()];
    }

    /** Opens (or returns) the direct conversation with a person, or the channel of a group. */
    public function open(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'user_id' => 'required_without:group_id|nullable|integer|exists:users,id',
            'group_id' => 'required_without:user_id|nullable|integer|exists:class_groups,id',
        ]);

        if (isset($data['group_id'])) {
            $group = ClassGroup::query()->findOrFail($data['group_id']);
            abort_unless($user->isAnyRole('admin', 'teacher') && $this->access->canAccessGroup($user, $group), 403, 'لا يمكنك فتح قناة هذه المجموعة.');
            $conversation = $this->chat->openGroup($user, $group);
        } else {
            $other = User::query()->findOrFail($data['user_id']);
            abort_unless($this->access->canChat($user, $other), 403, 'لا يمكنك مراسلة هذا الشخص.');
            $conversation = $this->chat->openDirect($user, $other);
        }

        return response()->json($this->present($conversation, $user, $this->unread($user, [$conversation->id])), 201);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $conversations = $this->mine($user);
        $unread = $this->unread($user, $conversations->pluck('id')->all());

        return ['data' => $conversations->map(fn (Conversation $conversation) => $this->present($conversation, $user, $unread))->values()];
    }

    /**
     * A page of messages. No cursor: the newest ones. `before_id`: the ones
     * before that message, for scrolling back. `after_id`: what arrived
     * since, for catching up after a reconnect. Always oldest first.
     */
    public function messages(Request $request, Conversation $conversation)
    {
        $this->authorizeAccess($request->user(), $conversation);
        $params = $request->validate([
            'before_id' => 'nullable|integer|min:1', 'after_id' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|between:1,100',
        ]);
        $limit = (int) ($params['limit'] ?? 30);
        $query = Message::query()->withTrashed()->with('sender')->where('conversation_id', $conversation->id);

        if (isset($params['after_id'])) {
            $rows = $query->where('id', '>', $params['after_id'])->orderBy('id')->limit($limit + 1)->get();
            $hasMore = $rows->count() > $limit;
            $page = $rows->take($limit);
        } else {
            if (isset($params['before_id'])) {
                $query->where('id', '<', $params['before_id']);
            }
            $rows = $query->orderByDesc('id')->limit($limit + 1)->get();
            $hasMore = $rows->count() > $limit;
            $page = $rows->take($limit)->reverse()->values();
        }

        return ['data' => $page->map(fn (Message $message) => $message->present())->values(), 'has_more' => $hasMore];
    }

    public function send(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        $this->authorizeAccess($user, $conversation);
        abort_unless($this->access->canPost($user, $conversation), 403, $conversation->isGroup()
            ? 'هذه القناة للإعلانات فقط.'
            : 'المحادثة غير متاحة.');
        $data = $request->validate([
            'body' => 'required|string|max:4000',
            'client_id' => 'nullable|string|max:40',
        ], ['body.required' => 'اكتب رسالة أولاً.', 'body.max' => 'الرسالة طويلة جداً.']);
        $body = trim($data['body']);
        abort_if($body === '', 422, 'اكتب رسالة أولاً.');

        $message = $this->chat->send($user, $conversation, $body, $data['client_id'] ?? null);

        return response()->json($message->present(), $message->wasRecentlyCreated ? 201 : 200);
    }

    public function read(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        $this->authorizeAccess($user, $conversation);
        $this->chat->markRead($user, $conversation, (int) $request->validate(['message_id' => 'required|integer|min:1'])['message_id']);

        return response()->noContent();
    }

    /** Staff switch a group channel between open discussion and announcements only. */
    public function update(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        abort_unless($conversation->isGroup() && $user->isAnyRole('admin', 'teacher'), 403);
        $this->authorizeAccess($user, $conversation);
        $conversation->update($request->validate(['announce_only' => 'required|boolean']));

        return $this->present($conversation->fresh(), $user, $this->unread($user, [$conversation->id]));
    }

    public function destroyMessage(Request $request, Message $message)
    {
        $user = $request->user();
        $conversation = $message->conversation;
        $this->authorizeAccess($user, $conversation);
        abort_unless($message->sender_id === $user->id || $user->isAnyRole('admin'), 403, 'لا يمكنك حذف هذه الرسالة.');

        $message->delete();
        MessageDeleted::dispatch($conversation->id, $message->id);

        return response()->noContent();
    }

    public function report(Request $request, Message $message)
    {
        $user = $request->user();
        $this->authorizeAccess($user, $message->conversation);
        $reason = $request->validate(['reason' => 'required|string|max:500'])['reason'];
        MessageReport::query()->firstOrCreate(['message_id' => $message->id, 'reporter_id' => $user->id], ['reason' => $reason]);

        return response()->json(['reported' => true], 201);
    }

    public function block(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'لا يمكنك حظر نفسك.');
        UserBlock::query()->firstOrCreate(['blocker_id' => $request->user()->id, 'blocked_id' => $user->id]);

        return response()->noContent();
    }

    public function unblock(Request $request, User $user)
    {
        UserBlock::query()->where(['blocker_id' => $request->user()->id, 'blocked_id' => $user->id])->delete();

        return response()->noContent();
    }

    // Management oversight ----------------------------------------------------

    /** Every conversation, with who is in it and how many messages were reported. */
    public function adminIndex(Request $request)
    {
        abort_unless($request->user()->isAnyRole('admin'), 403);
        $reports = MessageReport::query()->join('messages', 'messages.id', '=', 'message_reports.message_id')
            ->selectRaw('messages.conversation_id, count(*) as total')->groupBy('messages.conversation_id')
            ->pluck('total', 'conversation_id');

        return ['data' => Conversation::query()->with('group')->orderByDesc('last_message_at')->limit(200)->get()
            ->map(fn (Conversation $conversation) => [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'title' => $conversation->isGroup()
                    ? $conversation->group?->name
                    : User::query()->whereKey(explode(':', $conversation->direct_key))->pluck('name')->implode(' ↔ '),
                'last_message_at' => $conversation->last_message_at?->toISOString(),
                'reports' => (int) ($reports[$conversation->id] ?? 0),
            ])->values()];
    }

    /** Reads any conversation; each read is written to the audit log. */
    public function adminMessages(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        abort_unless($user->isAnyRole('admin'), 403);
        ChatAuditLog::query()->create(['staff_id' => $user->id, 'conversation_id' => $conversation->id, 'action' => 'read']);

        return [
            'data' => Message::query()->withTrashed()->with('sender')->where('conversation_id', $conversation->id)
                ->orderByDesc('id')->limit(200)->get()->reverse()->values()
                ->map(fn (Message $message) => $message->present() + ['body_if_deleted' => $message->trashed() ? $message->body : null]),
        ];
    }

    // Helpers -------------------------------------------------------------------

    private function authorizeAccess(User $user, Conversation $conversation): void
    {
        abort_unless($this->access->canAccess($user, $conversation), 403, 'لا تملك صلاحية هذه المحادثة.');
    }

    /** @return \Illuminate\Support\Collection<int, Conversation> */
    private function mine(User $user)
    {
        $direct = Conversation::query()->where('type', Conversation::DIRECT)
            ->where(fn ($query) => $query->where('direct_key', 'like', "{$user->id}:%")->orWhere('direct_key', 'like', "%:{$user->id}"))
            ->get();
        $groups = Conversation::query()->where('type', Conversation::GROUP)->with('group')->get();

        return $direct->merge($groups)
            ->filter(fn (Conversation $conversation) => $this->access->canAccess($user, $conversation))
            ->sortByDesc(fn (Conversation $conversation) => $conversation->last_message_at?->timestamp ?? $conversation->created_at->timestamp)
            ->values();
    }

    /**
     * Unread messages per conversation, derived from each person's read
     * position (never a stored counter): messages from others after it.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function unread(User $user, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('messages')
            ->leftJoin('conversation_members as cm', fn ($join) => $join->on('cm.conversation_id', '=', 'messages.conversation_id')->where('cm.user_id', '=', $user->id))
            ->whereIn('messages.conversation_id', $ids)
            ->whereNull('messages.deleted_at')
            ->where('messages.sender_id', '!=', $user->id)
            ->whereRaw('messages.id > coalesce(cm.last_read_message_id, 0)')
            ->groupBy('messages.conversation_id')
            ->selectRaw('messages.conversation_id, count(*) as total')
            ->pluck('total', 'conversation_id')->map(fn ($total) => (int) $total)->all();
    }

    private function present(Conversation $conversation, User $user, array $unread): array
    {
        $other = $conversation->isGroup() ? null : $this->access->otherParty($conversation, $user);
        $last = $conversation->last_message_id ? Message::query()->withTrashed()->with('sender')->find($conversation->last_message_id) : null;

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'title' => $conversation->isGroup() ? ($conversation->group?->name ?? '—') : ($other?->name ?? '—'),
            'group_id' => $conversation->group_id,
            'counterpart' => $other === null ? null : ['id' => $other->id, 'name' => $other->name, 'role' => $other->role],
            'announce_only' => $conversation->announce_only,
            'last_message' => $last?->present(),
            'unread' => $unread[$conversation->id] ?? 0,
            'can_post' => $this->access->canPost($user, $conversation),
            'updated_at' => ($conversation->last_message_at ?? $conversation->created_at)?->toISOString(),
        ];
    }
}
