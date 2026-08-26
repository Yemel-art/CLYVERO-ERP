<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class EloquentUserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected function model(): string
    {
        return User::class;
    }

    public function findByEmail(string $email, ?string $schoolSlug = null): ?User
    {
        $matches = $this->newQuery()
            ->with(['role', 'school'])
            ->where('email', $email)
            ->when($schoolSlug, function ($query) use ($schoolSlug): void {
                $identifier = trim($schoolSlug);
                $query->whereHas('school', fn ($school) => $school
                    ->where(fn ($match) => $match
                        ->whereRaw('LOWER(slug) = ?', [strtolower($identifier)])
                        ->orWhereRaw('UPPER(school_code) = ?', [strtoupper($identifier)])));
            })
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    public function incrementFailedAttempts(User $user): User
    {
        $user->failed_login_attempts++;
        $user->save();

        return $user->refresh();
    }

    public function resetFailedAttempts(User $user): User
    {
        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        $user->save();

        return $user->refresh();
    }

    public function lockUntil(User $user, \DateTimeInterface $until): User
    {
        $user->locked_until = $until;
        $user->save();

        return $user->refresh();
    }

    public function markLoggedIn(User $user, string $ip): User
    {
        $user->last_login_at = now();
        $user->last_login_ip = $ip;
        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        $user->save();

        return $user->refresh();
    }
}
