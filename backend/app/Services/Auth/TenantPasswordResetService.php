<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\TenantPasswordResetNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

final class TenantPasswordResetService
{
    public function send(User $user, ?string $ip): void
    {
        $plain = Str::random(64);
        DB::transaction(function () use ($user, $plain, $ip): void {
            $existingId = DB::table('tenant_password_reset_challenges')
                ->where('user_id', $user->id)->lockForUpdate()->value('id');
            DB::table('tenant_password_reset_challenges')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'id' => $existingId ?: (string) Str::uuid(),
                    'token_hash' => hash('sha256', $plain),
                    'expires_at' => now()->addMinutes((int) config('auth.passwords.users.expire', 30)),
                    'used_at' => null,
                    'requested_ip' => $ip,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        });
        $user->notify(new TenantPasswordResetNotification($plain));
    }

    /** @param callable(): void $callback */
    public function consume(User $user, string $plainToken, callable $callback): bool
    {
        return DB::transaction(function () use ($user, $plainToken, $callback): bool {
            $challenge = DB::table('tenant_password_reset_challenges')
                ->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $challenge || $challenge->used_at !== null || now()->greaterThan(Carbon::parse($challenge->expires_at))
                || ! hash_equals((string) $challenge->token_hash, hash('sha256', $plainToken))) {
                return false;
            }
            $callback();
            DB::table('tenant_password_reset_challenges')->where('user_id', $user->id)->update([
                'used_at' => now(), 'updated_at' => now(),
            ]);
            return true;
        });
    }

    public function deleteFor(User $user): void
    {
        DB::table('tenant_password_reset_challenges')->where('user_id', $user->id)->delete();
    }
}
