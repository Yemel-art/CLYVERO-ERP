<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\ParentGuardian;
use App\Models\Payment;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    public function test_repeated_invoice_generation_is_idempotent(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        FeeStructure::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Tuition', 'category' => 'tuition',
            'amount' => '108000.00', 'frequency' => 'annual', 'is_required' => true,
        ]);
        $student = Student::factory()->create([
            'school_id' => $this->year->school_id,
            'academic_year_id' => $this->year->id,
        ]);
        $payload = ['student_id' => $student->id, 'academic_year_id' => $this->year->id];

        $first = $this->postJson('/api/v1/finance/invoices/generate', $payload)->assertCreated();
        $second = $this->postJson('/api/v1/finance/invoices/generate', $payload)->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Invoice::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $this->year->id)
            ->count());
    }

    public function test_decimal_payments_keep_an_exact_balance(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        FeeStructure::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Exact tuition', 'category' => 'tuition',
            'amount' => '100000.55', 'frequency' => 'annual', 'is_required' => true,
        ]);
        $student = Student::factory()->create([
            'school_id' => $this->year->school_id,
            'academic_year_id' => $this->year->id,
        ]);
        $invoiceId = $this->postJson('/api/v1/finance/invoices/generate', [
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/finance/invoices/{$invoiceId}/payments", [
            'paid_at' => now()->toDateString(),
            'amount' => '40000.11',
            'method' => 'cash',
        ])->assertCreated();

        $invoice = Invoice::query()->findOrFail($invoiceId);
        $this->assertSame('40000.11', $invoice->paid);
        $this->assertSame('60000.44', $invoice->balance);
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

    public function test_parent_finance_access_is_limited_to_linked_children(): void
    {
        $parentRole = Role::query()->where('name', UserRole::Parent->value)->firstOrFail();
        $parentUser = User::factory()->create([
            'school_id' => $this->admin->school_id,
            'role_id' => $parentRole->id,
        ]);
        $parent = ParentGuardian::factory()->create([
            'school_id' => $this->admin->school_id,
            'user_id' => $parentUser->id,
            'is_active' => true,
        ]);
        $ownStudent = Student::factory()->create([
            'school_id' => $this->admin->school_id,
            'academic_year_id' => $this->year->id,
        ]);
        $otherStudent = Student::factory()->create([
            'school_id' => $this->admin->school_id,
            'academic_year_id' => $this->year->id,
        ]);
        $parent->students()->attach($ownStudent->id, [
            'id' => (string) Str::uuid(),
            'relationship' => 'Parent',
            'is_primary' => true,
        ]);

        $ownInvoice = $this->privacyInvoice($ownStudent, 'PRIV-OWN');
        $otherInvoice = $this->privacyInvoice($otherStudent, 'PRIV-OTHER');
        $otherPayment = Payment::query()->create([
            'receipt_number' => 'PRIV-RCT-OTHER',
            'invoice_id' => $otherInvoice->id,
            'student_id' => $otherStudent->id,
            'paid_at' => now(),
            'amount' => 500,
            'method' => 'cash',
            'received_by' => $this->admin->id,
        ]);

        Sanctum::actingAs($parentUser, ['*']);

        $this->getJson('/api/v1/finance/invoices')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownInvoice->id);
        $this->getJson("/api/v1/finance/invoices/{$otherInvoice->id}")->assertForbidden();
        $this->getJson("/api/v1/finance/students/{$otherStudent->id}/balance")->assertForbidden();
        $this->get("/api/v1/finance/payments/{$otherPayment->id}/receipt")->assertForbidden();
        $this->getJson('/api/v1/finance/summary')->assertForbidden();
    }

    private function privacyInvoice(Student $student, string $number): Invoice
    {
        return Invoice::query()->create([
            'invoice_number' => $number,
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'issued_at' => now(),
            'due_at' => now()->addMonth(),
            'subtotal' => 1000,
            'total' => 1000,
            'paid' => 0,
            'balance' => 1000,
            'status' => Invoice::STATUS_ISSUED,
            'created_by' => $this->admin->id,
        ]);
    }
}
