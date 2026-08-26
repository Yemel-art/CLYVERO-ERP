<?php

declare(strict_types=1);

namespace Tests\Feature\Student;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CarteScolaireImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_previews_then_approves_an_exact_class_match(): void
    {
        $this->seed();
        $admin = User::query()->whereHas('role', fn ($query) => $query->where('name', 'administrator'))->firstOrFail();
        Sanctum::actingAs($admin, ['*']);

        $year = AcademicYear::query()->where('school_id', $admin->school_id)->where('status', AcademicYear::STATUS_ACTIVE)->firstOrFail();
        SchoolClass::create([
            'academic_year_id' => $year->id,
            'name' => 'Form 3 A',
            'grade_level' => 'Form 3',
            'capacity' => 40,
            'is_active' => true,
        ]);
        $file = UploadedFile::fake()->createWithContent('carte-scolaire.csv', "matricule,first_name,last_name,class,gender,date_of_birth\nCS-001,Alice,Ngono,Form 3 A,female,2011-04-12\n");

        $preview = $this->post('/api/v1/students/imports/carte-scolaire/preview', [
            'file' => $file,
            'academic_year_id' => $year->id,
            'cycle' => 'secondary_general',
        ]);

        $preview->assertCreated()
            ->assertJsonPath('data.summary.ready', 1)
            ->assertJsonPath('data.preview.0.status', 'ready');

        $this->postJson('/api/v1/students/imports/carte-scolaire/'.$preview->json('data.import.id').'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', 'imported')
            ->assertJsonPath('data.summary.imported', 1);

        $this->assertDatabaseHas('students', ['school_id' => $admin->school_id, 'admission_number' => 'CS-001', 'class_id' => SchoolClass::query()->value('id')]);
        $this->assertDatabaseCount('student_enrollments', 1);
    }
}
