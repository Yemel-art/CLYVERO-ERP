<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        self::$sequence++;
        $gender = fake()->randomElement([Gender::Male, Gender::Female]);
        $year   = (int) now()->year;

        return [
            'school_id'         => fn () => School::query()->value('id') ?? School::factory()->create()->id,
            'admission_number'  => sprintf('TL-%d-%04d', $year, self::$sequence),
            'first_name'        => fake()->firstName($gender === Gender::Male ? 'male' : 'female'),
            'last_name'         => fake()->lastName(),
            'middle_name'       => fake()->boolean(40) ? fake()->firstName() : null,
            'gender'            => $gender,
            'date_of_birth'     => fake()->dateTimeBetween('-18 years', '-6 years')->format('Y-m-d'),
            'place_of_birth'    => fake()->city(),
            'nationality'       => 'Cameroonian',
            'religion'          => fake()->randomElement([null, 'Christian', 'Muslim', null]),

            'email'   => fake()->unique()->safeEmail(),
            'phone'   => '+237' . fake()->numerify('6########'),
            'address' => fake()->streetAddress(),
            'city'    => fake()->randomElement(['Yaoundé', 'Douala', 'Bafoussam', 'Garoua']),
            'country' => 'Cameroon',

            'academic_year_id' => AcademicYear::active()->value('id')
                ?? AcademicYear::factory()->create([
                    'school_id' => \App\Models\School::factory(),
                ])->id,

            'enrollment_date'  => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'previous_school'  => fake()->boolean(50) ? fake()->company() . ' School' : null,

            'emergency_contact_name'         => fake()->name(),
            'emergency_contact_phone'        => '+237' . fake()->numerify('6########'),
            'emergency_contact_relationship' => fake()->randomElement(['Father', 'Mother', 'Uncle', 'Aunt', 'Guardian']),

            'blood_group'        => fake()->randomElement([null, 'A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-']),
            'allergies'          => null,
            'medical_conditions' => null,

            'status'  => StudentStatus::Active,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => StudentStatus::Archived])
            ->afterCreating(fn (Student $s) => $s->delete());
    }

    public function male(): static
    {
        return $this->state(fn () => [
            'gender'     => Gender::Male,
            'first_name' => fake()->firstNameMale(),
        ]);
    }

    public function female(): static
    {
        return $this->state(fn () => [
            'gender'     => Gender::Female,
            'first_name' => fake()->firstNameFemale(),
        ]);
    }
}
