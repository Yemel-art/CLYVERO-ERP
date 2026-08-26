<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private SchoolClass $class;
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::query()->whereHas('role', fn ($q) => $q->where('name', 'administrator'))->firstOrFail();

        $year = AcademicYear::query()->where('status', AcademicYear::STATUS_ACTIVE)->firstOrFail();
        $this->class = SchoolClass::create([
            'academic_year_id' => $year->id,
            'name' => 'Form 2A', 'grade_level' => 'Form 2', 'section' => 'A',
            'capacity' => 30, 'is_active' => true,
        ]);
        for ($i = 1; $i <= 3; $i++) {
            $this->students[] = Student::create([
                'school_id' => $year->school_id,
                'academic_year_id' => $year->id,
                'enrollment_date' => $year->start_date,
                'admission_number' => sprintf('CLY-2026-%04d', $i),
                'class_id' => $this->class->id,
                'first_name' => 'Student',
                'last_name' => "#{$i}",
                'date_of_birth' => '2010-04-12',
                'gender' => $i % 2 === 0 ? 'female' : 'male',
                'status' => 'active',
            ]);
        }
    }

    public function test_opening_a_session_is_idempotent_for_same_class_and_date(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $first = $this->postJson('/api/v1/attendance/sessions', [
            'class_id' => $this->class->id,
            'date' => '2026-10-01',
        ]);
        $first->assertOk();
        $firstId = $first->json('data.id');

        $second = $this->postJson('/api/v1/attendance/sessions', [
            'class_id' => $this->class->id,
            'date' => '2026-10-01',
        ]);
        $second->assertOk();
        $this->assertSame($firstId, $second->json('data.id'), 'Same date returns the same session.');
    }

    public function test_records_can_be_saved_then_session_closed(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $sessionId = $this->postJson('/api/v1/attendance/sessions', [
            'class_id' => $this->class->id,
            'date' => '2026-10-02',
        ])->json('data.id');

        $entries = collect($this->students)->map(fn ($student, $index) => [
            'student_id' => $student->id,
            'status' => $index === 0 ? 'present' : ($index === 1 ? 'absent' : 'late'),
        ])->all();

        $this->postJson("/api/v1/attendance/sessions/{$sessionId}/records", ['entries' => $entries])
            ->assertOk();

        $this->postJson("/api/v1/attendance/sessions/{$sessionId}/close")->assertOk();

        $this->assertSame(AttendanceSession::STATUS_CLOSED, AttendanceSession::find($sessionId)->status);

        $this->postJson("/api/v1/attendance/sessions/{$sessionId}/records", ['entries' => $entries])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['session']);
    }
}
