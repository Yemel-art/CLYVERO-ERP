<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\ParentGuardian;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParentGuardian>
 */
class ParentGuardianFactory extends Factory
{
    protected $model = ParentGuardian::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $gender = fake()->randomElement([Gender::Male, Gender::Female]);
        return [
            'school_id'      => fn () => School::query()->value('id') ?? School::factory()->create()->id,
            'first_name'      => fake()->firstName($gender === Gender::Male ? 'male' : 'female'),
            'last_name'       => fake()->lastName(),
            'middle_name'     => null,
            'gender'          => $gender,
            'email'           => fake()->unique()->safeEmail(),
            'phone'           => '+237' . fake()->numerify('6########'),
            'alternate_phone' => null,
            'address'         => fake()->streetAddress(),
            'city'            => 'Yaoundé',
            'country'         => 'Cameroon',
            'occupation'      => fake()->jobTitle(),
            'workplace'       => fake()->company(),
            'national_id'     => fake()->numerify('############'),
            'is_active'       => true,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['is_active' => false])
            ->afterCreating(fn (ParentGuardian $p) => $p->delete());
    }
}
