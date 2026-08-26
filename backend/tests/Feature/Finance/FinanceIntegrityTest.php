<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class FinanceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_voided_payment_is_preserved_and_removed_from_collected_totals(): void
    {
        $this->seed();
        $admin = User::query()->whereHas('role', fn ($query) => $query->where('name', 'administrator'))->firstOrFail();
        $year = AcademicYear::query()->where('school_id', $admin->school_id)->where('status', 'active')->firstOrFail();
        $class = SchoolClass::query()->create([
            'academic_year_id' => $year->id, 'education_system' => 'secondary_general',
            'name' => 'Form 1', 'grade_level' => 'Form 1', 'cycle' => 'first_cycle',
            'language' => 'en', 'capacity' => 40, 'is_active' => true,
        ]);
        $student = Student::factory()->create([
            'school_id' => $admin->school_id, 'academic_year_id' => $year->id, 'class_id' => $class->id,
        ]);
        FeeStructure::query()->create([
            'academic_year_id' => $year->id, 'name' => 'Tuition', 'category' => 'tuition',
            'amount' => 108000, 'frequency' => 'annual', 'is_required' => true,
        ]);
        Sanctum::actingAs($admin, ['*']);

        $invoiceId = $this->postJson('/api/v1/finance/invoices/generate', [
            'student_id' => $student->id, 'academic_year_id' => $year->id,
        ])->assertCreated()->json('data.id');
        $paymentId = $this->postJson("/api/v1/finance/invoices/{$invoiceId}/payments", [
            'paid_at' => now()->toDateString(), 'amount' => 80000, 'method' => 'cash',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/finance/invoices/{$invoiceId}/cancel")->assertUnprocessable();
        $this->deleteJson("/api/v1/finance/payments/{$paymentId}", ['reason' => 'Cash entry was recorded twice.'])->assertOk();
        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'amount' => 80000]);
        $this->assertNotNull(\App\Models\Payment::query()->findOrFail($paymentId)->voided_at);
        $this->assertSame(0.0, (float) $this->getJson('/api/v1/finance/summary')->assertOk()->json('data.total_collected'));
        $this->assertSame(108000.0, (float) Invoice::query()->findOrFail($invoiceId)->balance);
    }
}
