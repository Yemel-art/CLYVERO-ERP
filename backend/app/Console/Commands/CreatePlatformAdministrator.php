<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use App\Rules\StrongPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

final class CreatePlatformAdministrator extends Command
{
    protected $signature = 'clyvero:platform-admin';
    protected $description = 'Create or rotate the school-independent Clyvero platform administrator';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->ask('Platform administrator email')));
        $firstName = trim((string) $this->ask('First name', 'Platform'));
        $lastName = trim((string) $this->ask('Last name', 'Administrator'));
        $password = (string) $this->secret('Strong password (input is hidden)');
        $validation = Validator::make(compact('email', 'password'), [
            'email' => ['required', 'email', 'max:160'],
            'password' => ['required', 'string', new StrongPassword()],
        ]);
        if ($validation->fails()) {
            foreach ($validation->errors()->all() as $message) $this->error($message);
            return self::FAILURE;
        }

        $conflict = User::withoutGlobalScopes()->whereRaw('LOWER(email) = ?', [$email])
            ->whereNotNull('school_id')->exists();
        if ($conflict && ! $this->confirm('A school user already uses this email. Continue with a separate platform account?', false)) {
            return self::FAILURE;
        }

        $role = Role::query()->where('name', UserRole::SuperAdministrator->value)->firstOrFail();
        User::withoutGlobalScopes()->updateOrCreate(
            ['school_id' => null, 'email' => $email],
            [
                'role_id' => $role->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'password' => Hash::make($password),
                'is_active' => true,
                'email_verified_at' => now(),
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ],
        );
        $this->info('Platform administrator is ready. The password was not logged or displayed.');

        return self::SUCCESS;
    }
}
