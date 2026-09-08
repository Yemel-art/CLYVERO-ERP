<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;

class SubjectPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('subject.view'); }
    public function view(User $user, Subject $_s): bool { return $user->hasPermission('subject.view'); }
    public function create(User $user): bool
    {
        return $user->hasPermission('subject.create') && ($user->isAdministrator() || $user->isSecretary());
    }
    public function update(User $user, Subject $_s): bool
    {
        return $user->hasPermission('subject.edit') && ($user->isAdministrator() || $user->isSecretary());
    }
    public function archive(User $user, Subject $_s): bool
    {
        return $user->hasPermission('subject.delete') && $user->isAdministrator();
    }
    public function restore(User $user, Subject $_s): bool
    {
        return $user->hasPermission('subject.delete') && $user->isAdministrator();
    }
    public function delete(User $user, Subject $_s): bool { return $user->isAdministrator(); }
}
