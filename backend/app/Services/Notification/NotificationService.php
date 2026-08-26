<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\Notification;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class NotificationService extends BaseService
{
    /**
     * Send one notification to one user.
     *
     * @param array<string, mixed> $data
     */
    public function notify(User $user, string $title, ?string $body = null, string $type = 'info', array $data = []): Notification
    {
        return Notification::create([
            'user_id'   => $user->id,
            'sender_id' => Auth::id(),
            'type'      => $type,
            'title'     => $title,
            'body'      => $body,
            'data'      => $data,
        ]);
    }

    /**
     * Broadcast to every user in a role.
     *
     * @return int Number of notifications created.
     */
    public function broadcastToRole(string $roleName, string $title, ?string $body = null, string $type = 'info'): int
    {
        $users = User::whereHas('role', fn ($q) => $q->where('name', $roleName))->get();
        $count = 0;
        foreach ($users as $u) {
            $this->notify($u, $title, $body, $type);
            $count++;
        }
        return $count;
    }

    /**
     * Broadcast to every active user.
     */
    public function broadcastAll(string $title, ?string $body = null, string $type = 'info'): int
    {
        return $this->transaction(function () use ($title, $body, $type): int {
            $users = User::where('is_active', true)->get();
            $rows = $users->map(fn ($u) => [
                'id'         => (string) Str::uuid(),
                'user_id'    => $u->id,
                'sender_id'  => Auth::id(),
                'type'       => $type,
                'title'      => $title,
                'body'       => $body,
                'data'       => null,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();
            if (! empty($rows)) {
                \DB::table('notifications')->insert($rows);
            }
            return count($rows);
        });
    }

    public function markRead(Notification $n): Notification
    {
        if (! $n->read_at) $n->update(['read_at' => now()]);
        return $n->fresh() ?? $n;
    }

    public function markAllRead(User $user): int
    {
        return Notification::where('user_id', $user->id)->whereNull('read_at')->update(['read_at' => now()]);
    }
}
