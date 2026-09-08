<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface extends RepositoryInterface
{
    public function findByEmail(string $email, ?string $schoolCode = null): ?User;

    public function incrementFailedAttempts(User $user): User;

    public function resetFailedAttempts(User $user): User;

    public function lockUntil(User $user, \DateTimeInterface $until): User;

    public function markLoggedIn(User $user, string $ip): User;
}
