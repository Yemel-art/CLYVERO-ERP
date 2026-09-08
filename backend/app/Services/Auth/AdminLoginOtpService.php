<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\AdminLoginOtpChallenge;
use App\Models\User;
use App\Notifications\AdminLoginOtpNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;

final class AdminLoginOtpService
{
    public const EXPIRES_IN_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    public function createChallenge(User $user, bool $rememberMe, string $ip): AdminLoginOtpChallenge
    {
        [$challenge, $code] = DB::transaction(function () use ($user, $rememberMe, $ip): array {
            AdminLoginOtpChallenge::query()
                ->where('user_id', $user->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            // Keep the table compact without making login depend on a scheduler.
            AdminLoginOtpChallenge::query()
                ->where('created_at', '<', now()->subDay())
                ->delete();

            $code = (string) random_int(100000, 999999);
            $challenge = AdminLoginOtpChallenge::create([
                'user_id' => $user->id,
                'code_hash' => Hash::make($code),
                'remember_me' => $rememberMe,
                'expires_at' => now()->addMinutes(self::EXPIRES_IN_MINUTES),
                'requested_ip' => $ip,
            ]);

            return [$challenge, $code];
        });

        try {
            $user->notify(new AdminLoginOtpNotification($code, self::EXPIRES_IN_MINUTES));
        } catch (Throwable $exception) {
            $challenge->delete();
            throw $exception;
        }

        return $challenge;
    }

    public function verify(string $challengeId, string $code): AdminLoginOtpChallenge
    {
        $result = DB::transaction(function () use ($challengeId, $code): array {
            $challenge = AdminLoginOtpChallenge::query()
                ->with(['user.role'])
                ->lockForUpdate()
                ->find($challengeId);

            if ($challenge === null || (! $challenge->user?->isAdministrator() && ! $challenge->user?->isSuperAdministrator())) {
                return ['status' => 'invalid'];
            }

            if ($challenge->consumed_at !== null || $challenge->expires_at->isPast()) {
                if ($challenge->consumed_at === null) {
                    $challenge->forceFill(['consumed_at' => now()])->save();
                }

                return ['status' => 'expired'];
            }

            if ($challenge->attempts >= self::MAX_ATTEMPTS) {
                $challenge->forceFill(['consumed_at' => now()])->save();
                return ['status' => 'locked'];
            }

            if (! Hash::check($code, $challenge->code_hash)) {
                $attempts = $challenge->attempts + 1;
                $challenge->forceFill([
                    'attempts' => $attempts,
                    'consumed_at' => $attempts >= self::MAX_ATTEMPTS ? now() : null,
                ])->save();

                return ['status' => $attempts >= self::MAX_ATTEMPTS ? 'locked' : 'invalid'];
            }

            $challenge->forceFill(['consumed_at' => now()])->save();

            return ['status' => 'valid', 'challenge' => $challenge];
        });

        if ($result['status'] !== 'valid') {
            $message = match ($result['status']) {
                'expired' => 'This verification code has expired. Please sign in again.',
                'locked' => 'Too many incorrect codes. Please sign in again to request a new code.',
                default => 'The verification code is invalid.',
            };

            throw ValidationException::withMessages(['code' => [$message]]);
        }

        return $result['challenge'];
    }

    public function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible . str_repeat('*', max(3, mb_strlen($local) - mb_strlen($visible))) . '@' . $domain;
    }
}
