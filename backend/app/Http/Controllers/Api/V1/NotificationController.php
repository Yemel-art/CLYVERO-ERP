<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Notification;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationController extends ApiController
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $q = Notification::where('user_id', $user->id);
        if ($request->boolean('unread_only')) $q->whereNull('read_at');

        $items = $q->orderByDesc('created_at')->limit((int) $request->integer('limit', 30))->get();

        return $this->ok([
            'data' => $items->map(fn (Notification $n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'title'      => $n->title,
                'body'       => $n->body,
                'data'       => $n->data,
                'read_at'    => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])->all(),
            'unread_count' => Notification::where('user_id', $user->id)->whereNull('read_at')->count(),
        ], 'Notifications retrieved.');
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()?->id, 403);
        $this->notifications->markRead($notification);
        return $this->ok(null, 'Marked as read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        $count = $this->notifications->markAllRead($user);
        return $this->ok(['marked' => $count], 'All marked as read.');
    }

    public function broadcast(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('notification.send'), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body'  => ['nullable', 'string', 'max:1000'],
            'type'  => ['nullable', 'in:info,success,warning,danger'],
            'role'  => ['nullable', 'string', 'exists:roles,name'],
        ]);

        $count = $data['role'] ?? null
            ? $this->notifications->broadcastToRole($data['role'], $data['title'], $data['body'] ?? null, $data['type'] ?? 'info')
            : $this->notifications->broadcastAll($data['title'], $data['body'] ?? null, $data['type'] ?? 'info');

        return $this->ok(['recipients' => $count], "Notification sent to {$count} users.");
    }
}
