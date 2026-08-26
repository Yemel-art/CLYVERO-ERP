<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private AcademicYear $year;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::query()->whereHas('role', fn ($q) => $q->where('name', 'administrator'))->firstOrFail();
        $this->year = AcademicYear::query()->where('status', AcademicYear::STATUS_ACTIVE)->firstOrFail();
    }

    public function test_invoice_generation_pulls_applicable_required_fees(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        // Required tuition (school-wide) + optional cafeteria
        FeeStructure::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Tuition', 'category' => 'tuition',
            'amount' => 250_000, 'frequency' => 'annual', 'is_required' => true,
        ]);
        FeeStructure::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Cafeteria', 'category' => 'cafeteria',
            'amount' => 50_000, 'frequency' => 'annual', 'is_required' => false,
        ]);

        $class = SchoolClass::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Form 1A', 'grade_level' => 'Form 1', 'section' => 'A',
            'capacity' => 40, 'is_active' => true,
        ]);
        $student = Student::create([
            'school_id' => $this->year->school_id,
            'academic_year_id' => $this->year->id,
            'enrollment_date' => $this->year->start_date,
            'admission_number' => 'TL-2026-0001',
            'class_id' => $class->id,
            'first_name' => 'Junior', 'last_name' => 'Nebasi',
            'date_of_birth' => '2010-04-12', 'gender' => 'male',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/finance/invoices/generate', [
            'student_id'       => $student->id,
            'academic_year_id' => $this->year->id,
        ]);
        $response->assertCreated();

        $invoiceId = $response->json('data.id');
        $invoice   = Invoice::find($invoiceId);
        $this->assertEquals(250_000.00, (float) $invoice->total, 'Only required fees are billed.');
        $this->assertCount(1, $invoice->items);
        $this->assertEquals(Invoice::STATUS_ISSUED, $invoice->status);
    }

    public function test_recording_a_payment_updates_invoice_status_and_balance(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        FeeStructure::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Tuition', 'category' => 'tuition',
            'amount' => 100_000, 'frequency' => 'annual', 'is_required' => true,
        ]);
        $class = SchoolClass::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Form 1B', 'grade_level' => 'Form 1', 'section' => 'B',
            'capacity' => 40, 'is_active' => true,
        ]);
        $student = Student::create([
            'school_id' => $this->year->school_id,
            'academic_year_id' => $this->year->id,
            'enrollment_date' => $this->year->start_date,
            'admission_number' => 'TL-2026-0002', 'class_id' => $class->id,
            'first_name' => 'Test', 'last_name' => 'Student',
            'date_of_birth' => '2010-04-12', 'gender' => 'female', 'status' => 'active',
        ]);

        $invoiceId = $this->postJson('/api/v1/finance/invoices/generate', [
            'student_id' => $student->id, 'academic_year_id' => $this->year->id,
        ])->json('data.id');

        // Partial payment
        $this->postJson("/api/v1/finance/invoices/{$invoiceId}/payments", [
            'paid_at' => now()->toDateString(),
            'amount'  => 40_000, 'method' => 'mtn_momo', 'reference' => 'MM-001',
        ])->assertCreated();

        $invoice = Invoice::find($invoiceId);
        $this->assertEquals(40_000.00, (float) $invoice->paid);
        $this->assertEquals(60_000.00, (float) $invoice->balance);
        $this->assertEquals(Invoice::STATUS_PARTIALLY_PAID, $invoice->status);

        // Final payment
        $this->postJson("/api/v1/finance/invoices/{$invoiceId}/payments", [
            'paid_at' => now()->toDateString(),
            'amount'  => 60_000, 'method' => 'cash',
        ])->assertCreated();

        $invoice->refresh();
        $this->assertEquals(0.0, (float) $invoice->balance);
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status);
    }

    public function test_administrator_cannot_generate_an_invoice_for_another_school_student(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $otherSchool = School::factory()->create();
        $otherYear = AcademicYear::factory()->create(['school_id' => $otherSchool->id]);
        $otherStudent = Student::factory()->create(['school_id' => $otherSchool->id, 'academic_year_id' => $otherYear->id]);

        $this->postJson('/api/v1/finance/invoices/generate', [
            'student_id' => $otherStudent->id,
            'academic_year_id' => $otherYear->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['student_id', 'academic_year_id']);
    }
}
