<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

final class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower(trim((string) env('PLATFORM_ADMIN_EMAIL')));
        $password = (string) env('PLATFORM_ADMIN_PASSWORD');
        if ($email === '' && $password === '') {
            return;
        }
        if ($email === '' || $password === '') {
            throw new \RuntimeException('PLATFORM_ADMIN_EMAIL and PLATFORM_ADMIN_PASSWORD must be configured together.');
        }

        $role = Role::query()->where('name', UserRole::SuperAdministrator->value)->firstOrFail();
        User::withoutGlobalScopes()->firstOrCreate(
            ['school_id' => null, 'email' => $email],
            [
                'role_id' => $role->id,
                'first_name' => env('PLATFORM_ADMIN_FIRST_NAME', 'Platform'),
                'last_name' => env('PLATFORM_ADMIN_LAST_NAME', 'Administrator'),
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
