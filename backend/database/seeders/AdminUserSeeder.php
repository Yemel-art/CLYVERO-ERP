<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use App\Models\School;
use Illuminate\Database\Seeder;

/**
 * Creates the bootstrap Administrator from .env values.
 *
 * The seeded password must be changed immediately after first login —
 * the README and CLI output both flag this. This seeder is idempotent:
 * subsequent runs update the role assignment but never touch an
 * already-changed password.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', UserRole::Administrator->value)->firstOrFail();

        $email = env('ADMIN_EMAIL', 'admin@example.test');
        $password = env('ADMIN_PASSWORD');
        if (app()->isProduction() && blank($password)) {
            throw new \RuntimeException('ADMIN_PASSWORD must be configured before production seeding.');
        }

        $schoolQuery = School::withoutGlobalScopes();
        $schoolCode = trim((string) env('SCHOOL_CODE'));
        $schoolEmail = strtolower(trim((string) env('SCHOOL_EMAIL')));
        if ($schoolCode !== '') {
            $schoolQuery->whereRaw('UPPER(school_code) = ?', [mb_strtoupper($schoolCode)]);
        } elseif ($schoolEmail !== '') {
            $schoolQuery->whereRaw('LOWER(email) = ?', [$schoolEmail]);
        }
        $school = $schoolQuery->first();
        if ($school === null) {
            throw new \RuntimeException('Seed the bootstrap school before creating its administrator.');
        }

        $existing = User::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->first();
        if ($existing !== null) {
            // Don't overwrite a real admin's password on reseed. Only
            // re-attach the role in case it drifted.
            if ($existing->role_id !== $adminRole->id) {
                $existing->update(['role_id' => $adminRole->id]);
            }
            return;
        }

        User::create([
            'school_id'  => $school->id,
            'role_id'    => $adminRole->id,
            'first_name' => env('ADMIN_FIRST_NAME', 'System'),
            'last_name'  => env('ADMIN_LAST_NAME', 'Administrator'),
            'email'      => $email,
            'password'   => $password ?: 'ChangeMe2026!',
            'is_active'  => true,
            'email_verified_at' => now(),
        ]);
    }
}
