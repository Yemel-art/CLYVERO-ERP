<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Term;
use App\Models\User;

class TermPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('term.view'); }
    public function view(User $user, Term $_t): bool { return $user->hasPermission('term.view'); }
    public function create(User $user): bool { return $user->isAdministrator() && $user->hasPermission('term.create'); }
    public function update(User $user, Term $_t): bool { return $user->isAdministrator() && $user->hasPermission('term.edit'); }
    public function delete(User $user, Term $_t): bool { return $user->isAdministrator() && $user->hasPermission('term.delete'); }
}
