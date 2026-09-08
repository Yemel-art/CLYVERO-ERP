<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AcademicYear;
use App\Models\User;

class AcademicYearPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('academic_year.view'); }
    public function view(User $user, AcademicYear $_y): bool { return $user->hasPermission('academic_year.view'); }
    public function create(User $user): bool { return $user->hasPermission('academic_year.create') && $user->isAdministrator(); }
    public function update(User $user, AcademicYear $_y): bool { return $user->hasPermission('academic_year.edit') && $user->isAdministrator(); }
    public function delete(User $user, AcademicYear $_y): bool { return $user->isAdministrator() && $user->hasPermission('academic_year.delete'); }
}
