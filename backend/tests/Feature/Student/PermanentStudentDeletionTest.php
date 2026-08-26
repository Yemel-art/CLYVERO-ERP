<?php

declare(strict_types=1);

namespace Tests\Feature\Student;

use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PermanentStudentDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_unpaid_test_student_but_not_financial_history(): void
    {
        $this->seed();
        $admin = User::query()->whereHas('role', fn ($query) => $query->where('name', 'administrator'))->firstOrFail();
        $year = AcademicYear::query()->where('school_id', $admin->school_id)->where('status', 'active')->firstOrFail();
        Sanctum::actingAs($admin, ['*']);
        $testStudent = Student::factory()->create(['school_id' => $admin->school_id, 'academic_year_id' => $year->id]);

        $this->deleteJson("/api/v1/students/{$testStudent->id}/permanent", [
            'confirmation' => $testStudent->admission_number,
            'reason' => 'test_record',
            'acknowledge_permanent' => true,
        ])->assertOk();
        $this->assertDatabaseMissing('students', ['id' => $testStudent->id]);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $testStudent->id, 'action' => 'permanently_deleted']);

        $paidStudent = Student::factory()->create(['school_id' => $admin->school_id, 'academic_year_id' => $year->id]);
        $invoice = Invoice::query()->create([
            'invoice_number' => 'TEST-INV-1', 'student_id' => $paidStudent->id, 'academic_year_id' => $year->id,
            'issued_at' => now(), 'due_at' => now()->addMonth(), 'subtotal' => 1000, 'total' => 1000,
            'paid' => 1000, 'balance' => 0, 'status' => 'paid', 'created_by' => $admin->id,
        ]);
        Payment::query()->create([
            'receipt_number' => 'TEST-RCT-1', 'invoice_id' => $invoice->id, 'student_id' => $paidStudent->id,
            'paid_at' => now(), 'amount' => 1000, 'method' => 'cash', 'received_by' => $admin->id,
        ]);
        $this->deleteJson("/api/v1/students/{$paidStudent->id}/permanent", [
            'confirmation' => $paidStudent->admission_number,
            'reason' => 'test_record',
            'acknowledge_permanent' => true,
        ])->assertUnprocessable();
        $this->assertDatabaseHas('students', ['id' => $paidStudent->id]);
    }
}
