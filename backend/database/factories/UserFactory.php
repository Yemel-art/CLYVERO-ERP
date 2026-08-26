<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'school_id'  => fn () => School::query()->value('id') ?? School::factory()->create()->id,
            'role_id'    => Role::firstOrCreate(
                ['name' => UserRole::Administrator->value],
                ['display_name' => 'Administrator'],
            )->id,
            'first_name' => fake()->firstName(),
            'last_name'  => fake()->lastName(),
            'email'      => fake()->unique()->safeEmail(),
            'phone'      => fake()->phoneNumber(),
            'password'   => Hash::make('Test1234!Pass'),
            'is_active'  => true,
            'email_verified_at'     => now(),
            'failed_login_attempts' => 0,
            'two_factor_enabled'    => false,
        ];
    }

    public function asRole(UserRole $role): static
    {
        return $this->state(fn () => [
            'role_id' => Role::firstOrCreate(
                ['name' => $role->value],
                ['display_name' => $role->displayName()],
            )->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function platformAdministrator(): static
    {
        return $this->state(fn () => [
            'school_id' => null,
            'role_id' => Role::firstOrCreate(
                ['name' => UserRole::SuperAdministrator->value],
                ['display_name' => UserRole::SuperAdministrator->displayName()],
            )->id,
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn () => [
            'failed_login_attempts' => 5,
            'locked_until'          => now()->addMinutes(15),
        ]);
    }
}
