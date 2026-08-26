<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    protected $model = School::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'school_code' => 'CLY-TST-'.strtoupper(fake()->unique()->bothify('####??')),
            'slug' => fake()->unique()->slug(2),
            'school_name' => fake()->unique()->company() . ' School',
            'slogan'      => fake()->catchPhrase(),
            'email'       => fake()->unique()->companyEmail(),
            'phone'       => '+237' . fake()->numerify('6########'),
            'address'     => fake()->address(),
            'city'        => 'Yaoundé',
            'country'     => 'Cameroon',
            'education_systems' => ['secondary_general', 'secondary_technical'],
            'is_active' => true,
        ];
    }
}
