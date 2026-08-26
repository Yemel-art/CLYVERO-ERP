<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ParentGuardian;
use App\Models\User;

class ParentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('parent.view');
    }

    public function view(User $user, ParentGuardian $parent): bool
    {
        if ($user->isParent()) {
            return $parent->user_id === $user->id;
        }

        return $user->hasPermission('parent.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('parent.create')
            && ($user->isAdministrator() || $user->isSecretary());
    }

    public function update(User $user, ParentGuardian $parent): bool
    {
        if (! $user->hasPermission('parent.edit')) {
            return false;
        }
        if ($user->isParent()) {
            // Parents may update only their own profile.
            return $parent->user_id === $user->id;
        }

        return $user->isAdministrator() || $user->isSecretary();
    }

    public function archive(User $user, ParentGuardian $_p): bool
    {
        return $user->hasPermission('parent.delete')
            && ($user->isAdministrator() || $user->isSecretary());
    }

    public function restore(User $user, ParentGuardian $_p): bool
    {
        return $user->hasPermission('parent.delete')
            && ($user->isAdministrator() || $user->isSecretary());
    }

    public function delete(User $user, ParentGuardian $_p): bool
    {
        return $user->hasPermission('parent.delete') && $user->isAdministrator();
    }
}
