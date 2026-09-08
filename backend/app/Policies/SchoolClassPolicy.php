<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;

class SchoolClassPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('class.view'); }
    public function view(User $user, SchoolClass $_c): bool { return $user->hasPermission('class.view'); }
    public function create(User $user): bool
    {
        return $user->hasPermission('class.create') && ($user->isAdministrator() || $user->isSecretary());
    }
    public function update(User $user, SchoolClass $_c): bool
    {
        return $user->hasPermission('class.edit') && ($user->isAdministrator() || $user->isSecretary());
    }
    public function archive(User $user, SchoolClass $_c): bool
    {
        return $user->hasPermission('class.delete') && $user->isAdministrator();
    }
    public function restore(User $user, SchoolClass $_c): bool
    {
        return $user->hasPermission('class.delete') && $user->isAdministrator();
    }
    public function delete(User $user, SchoolClass $_c): bool { return $user->isAdministrator(); }
}
