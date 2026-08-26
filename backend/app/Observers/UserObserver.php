<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;

/**
 * User lifecycle observer.
 *
 * Note: login/logout audit entries are written by AuthService (richer
 * context — IP, user-agent, success/failure). This observer covers
 * out-of-band User mutations only.
 *
 * Full audit-on-mutation will be added in Phase 12 (User Management module)
 * by mixing Auditable into the User model.
 */
class UserObserver
{
    public function created(User $user): void
    {
        // Reserved for future side-effects (welcome email is dispatched
        // explicitly via Event from UserService in Phase 12).
    }

    public function updated(User $user): void
    {
        // Reserved for future side-effects.
    }

    public function deleted(User $user): void
    {
        // Reserved for future side-effects.
    }
}
