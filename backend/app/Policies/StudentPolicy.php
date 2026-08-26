<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ParentGuardian;
use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('student.view'); }

    public function view(User $user, Student $student): bool
    {
        if (! $user->hasPermission('student.view')) return false;
        if ($user->isParent()) {
            return ParentGuardian::query()->where('user_id', $user->id)->where('is_active', true)
                ->whereHas('students', fn ($query) => $query->where('students.id', $student->id))->exists();
        }

        return true;
    }

    public function create(User $user): bool { return $user->hasPermission('student.create'); }
    public function update(User $user, Student $_student): bool
    {
        return $user->hasPermission('student.edit') && ($user->isAdministrator() || $user->isSecretary());
    }
    public function archive(User $user, Student $_student): bool
    {
        return $user->hasPermission('student.archive') && ($user->isAdministrator() || $user->isSecretary());
    }
    public function restore(User $user, Student $_student): bool
    {
        return $user->hasPermission('student.restore') && ($user->isAdministrator() || $user->isSecretary());
    }
    public function delete(User $user, Student $_student): bool
    {
        return $user->isAdministrator() && $user->hasPermission('student.delete');
    }
    public function export(User $user): bool { return $user->hasPermission('student.export'); }
}
