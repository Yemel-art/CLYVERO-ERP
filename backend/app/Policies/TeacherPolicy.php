<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;

class TeacherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('teacher.view');
    }

    public function view(User $user, Teacher $_t): bool
    {
        return $user->hasPermission('teacher.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('teacher.create')
            && ($user->isAdministrator() || $user->isSecretary());
    }

    public function update(User $user, Teacher $teacher): bool
    {
        if (! $user->hasPermission('teacher.edit')) {
            return false;
        }
        // Teachers may update only their own record.
        if ($user->isTeacher()) {
            return $teacher->user_id === $user->id;
        }
        return $user->isAdministrator() || $user->isSecretary();
    }

    public function archive(User $user, Teacher $_t): bool
    {
        return $user->hasPermission('teacher.delete') && $user->isAdministrator();
    }

    public function restore(User $user, Teacher $_t): bool
    {
        return $user->hasPermission('teacher.delete') && $user->isAdministrator();
    }

    public function delete(User $user, Teacher $_t): bool
    {
        return $user->hasPermission('teacher.delete') && $user->isAdministrator();
    }
}
