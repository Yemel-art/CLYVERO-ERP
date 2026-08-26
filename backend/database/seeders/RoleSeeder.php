<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (UserRole::cases() as $role) {
            Role::updateOrCreate(
                ['name' => $role->value],
                [
                    'display_name' => $role->displayName(),
                    'description'  => $role->description(),
                ]
            );
        }
    }
}
