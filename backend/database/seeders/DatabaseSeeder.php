<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Master seeder. Order matters:
 *   1. Roles      — referenced by everyone.
 *   2. Permissions — references Roles via the pivot.
 *   3. School      — references AcademicYear; AcademicYear references School.
 *   4. Admin User  — references Role.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            PlatformAdminSeeder::class,
            SchoolSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
