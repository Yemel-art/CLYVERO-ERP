<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\Student\StudentDTO;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\School;
use App\Models\Student;
use App\Repositories\Contracts\StudentRepositoryInterface;
use App\Services\Student\StudentService;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StudentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $school = School::factory()->create();
        AcademicYear::factory()->create([
            'school_id' => $school->id,
            'status' => AcademicYear::STATUS_ACTIVE,
        ]);
    }

    private function service(): StudentService
    {
        return app(StudentService::class);
    }

    public function test_register_preserves_manual_matricule_and_sets_active_status(): void
    {
        $dto = new StudentDTO(
            firstName: 'Junior',
            lastName: 'Tah',
            gender: Gender::Male,
            dateOfBirth: CarbonImmutable::parse('2010-05-20'),
            enrollmentDate: CarbonImmutable::today(),
            admissionNumber: 'MEC-2026-0042',
        );

        $student = $this->service()->register($dto);

        $this->assertInstanceOf(Student::class, $student);
        $this->assertSame(StudentStatus::Active, $student->status);
        $this->assertSame('MEC-2026-0042', $student->admission_number);
        $this->assertSame('MEC-2026-0042', $student->official_matricule);
    }

    public function test_archive_sets_status_and_soft_deletes(): void
    {
        $student = Student::factory()->create();
        $archived = $this->service()->archive($student);
        $this->assertSame(StudentStatus::Archived, $archived->status);
        $this->assertNotNull($archived->deleted_at);
    }

    public function test_restore_reverses_archive(): void
    {
        $student = Student::factory()->archived()->create();
        $restored = $this->service()->restore($student);
        $this->assertSame(StudentStatus::Active, $restored->status);
        $this->assertNull($restored->deleted_at);
    }

    public function test_statistics_counts_by_status(): void
    {
        Student::factory()->count(4)->create();
        Student::factory()->archived()->count(2)->create();
        $stats = $this->service()->statistics();
        $this->assertSame(4, $stats['total']);
        $this->assertSame(2, $stats['archived']);
    }

    public function test_repository_binding_resolves(): void
    {
        $this->assertInstanceOf(
            StudentRepositoryInterface::class,
            app(StudentRepositoryInterface::class),
        );
    }
}
