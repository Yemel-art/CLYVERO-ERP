<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\TeacherStatus;
use App\Models\Teacher;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Teacher>
 */
class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        self::$sequence++;
        $year = (int) now()->year;
        $gender = fake()->randomElement([Gender::Male, Gender::Female]);

        return [
            'school_id'      => fn () => School::query()->value('id') ?? School::factory()->create()->id,
            'employee_number' => sprintf('TL-T-%d-%04d', $year, self::$sequence),
            'first_name'      => fake()->firstName($gender === Gender::Male ? 'male' : 'female'),
            'last_name'       => fake()->lastName(),
            'middle_name'     => null,
            'gender'          => $gender,
            'email'           => fake()->unique()->safeEmail(),
            'phone'           => '+237' . fake()->numerify('6########'),
            'date_of_birth'   => fake()->dateTimeBetween('-60 years', '-25 years')->format('Y-m-d'),
            'nationality'     => 'Cameroonian',
            'address'         => fake()->streetAddress(),
            'city'            => 'Yaoundé',
            'country'         => 'Cameroon',
            'qualification'   => fake()->randomElement(['BSc', 'MSc', 'BEd', 'MEd', 'PhD']) . ' ' . fake()->randomElement(['Mathematics', 'English', 'Physics', 'History']),
            'specialization'  => fake()->randomElement(['Algebra', 'Literature', 'Mechanics', 'Modern History']),
            'years_of_experience' => fake()->numberBetween(0, 30),
            'hire_date'       => fake()->dateTimeBetween('-15 years', '-1 month')->format('Y-m-d'),
            'salary'          => fake()->numberBetween(150_000, 1_200_000),
            'emergency_contact_name'  => fake()->name(),
            'emergency_contact_phone' => '+237' . fake()->numerify('6########'),
            'status'          => TeacherStatus::Active,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => TeacherStatus::Archived])
            ->afterCreating(fn (Teacher $t) => $t->delete());
    }
}
