<?php

declare(strict_types=1);

namespace Tests\Feature\Student;

use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class OfficialStudentImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_malformed_xlsx_returns_validation_error_instead_of_server_error(): void
    {
        $this->seed();
        $admin = User::query()->whereHas('role', fn ($query) => $query->where('name', 'administrator'))->firstOrFail();
        Sanctum::actingAs($admin, ['*']);
        $file = UploadedFile::fake()->create(
            'students.xlsx',
            1,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $this->postJson('/api/v1/students/imports/analyze', ['file' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_official_csv_preview_confirm_and_repeat_are_idempotent(): void
    {
        $this->seed();
        $admin = User::query()->whereHas('role', fn ($query) => $query->where('name', 'administrator'))->firstOrFail();
        $year = AcademicYear::query()->where('school_id', $admin->school_id)->where('status', 'active')->firstOrFail();
        $class = SchoolClass::query()->create([
            'academic_year_id' => $year->id, 'education_system' => 'secondary_general',
            'name' => '6ème A', 'grade_level' => '6ème', 'cycle' => 'first_cycle',
            'language' => 'fr', 'capacity' => 60, 'is_active' => true,
        ]);
        Sanctum::actingAs($admin, ['*']);
        $file = UploadedFile::fake()->createWithContent('cartes-scolaire.csv', implode("\n", [
            'Matricule National,Noms et Prénoms,Classe,Sexe,Date de naissance,Lieu de naissance',
            'MINSEC-2026-001,NGONO Divine,6ème A,F,2012-04-13,Yaoundé',
        ]));

        $preview = $this->postJson('/api/v1/students/imports/preview', [
            'file' => $file, 'academic_year_id' => $year->id,
        ])->assertCreated()->assertJsonPath('data.summary.valid', 1);
        $importId = $preview->json('data.id');
        $payload = ['class_mapping' => [['source_class' => '6ème A', 'class_id' => $class->id]]];
        $this->postJson("/api/v1/students/imports/{$importId}/confirm", $payload)
            ->assertOk()->assertJsonPath('data.summary.imported', 1);
        $this->postJson("/api/v1/students/imports/{$importId}/confirm", $payload)->assertOk();

        $this->assertSame(1, Student::query()->where('official_matricule', 'MINSEC-2026-001')->count());
        $student = Student::query()->where('official_matricule', 'MINSEC-2026-001')->firstOrFail();
        $this->assertSame('NGONO', $student->last_name);
        $this->assertSame('Divine', $student->first_name);
        $this->assertDatabaseHas('student_enrollments', [
            'student_id' => $student->id, 'academic_year_id' => $year->id, 'class_id' => $class->id,
        ]);
    }

    public function test_mapped_import_enrolls_bills_without_payment_then_preserves_duplicates(): void
    {
        $this->seed();
        $admin = User::query()->whereHas('role', fn ($query) => $query->where('name', 'administrator'))->firstOrFail();
        $year = AcademicYear::query()->where('school_id', $admin->school_id)->active()->firstOrFail();
        Sanctum::actingAs($admin, ['*']);

        $makeClass = fn (string $name, string $level, string $cycle): SchoolClass => SchoolClass::query()->create([
            'academic_year_id' => $year->id, 'education_system' => 'secondary_general',
            'name' => $name, 'grade_level' => $level, 'cycle' => $cycle, 'language' => 'en',
            'capacity' => 60, 'is_active' => true,
        ]);
        $form4 = $makeClass('Form 4', 'Form 4', 'first_cycle');
        $form5 = $makeClass('Form 5', 'Form 5', 'first_cycle');
        $lowerSixth = $makeClass('Lower Sixth', 'Lower Sixth', 'second_cycle');
        foreach ([[$form4, 50_000], [$form5, 65_000], [$lowerSixth, 70_000]] as [$class, $amount]) {
            FeeStructure::query()->create([
                'academic_year_id' => $year->id, 'class_id' => $class->id,
                'name' => 'Annual school fees', 'category' => 'tuition', 'amount' => $amount,
                'frequency' => 'annual', 'is_required' => true,
            ]);
        }

        $csv = implode("\n", [
            'Learner,Registration ID,Imported Level,Sex,Birth',
            'DOE John,2026001,Form 4,M,2011-02-03',
            'NGONO Divine,2026002,Form 5,F,',
            'TALLA Mercy,2026003,Lower Sixth,,2009-06-08',
        ]);
        $file = UploadedFile::fake()->createWithContent('authorized-export.csv', $csv);
        $analysis = $this->postJson('/api/v1/students/imports/analyze', ['file' => $file])
            ->assertOk()
            ->assertJsonPath('data.active_academic_year.id', $year->id)
            ->assertJsonPath('data.row_count', 3);
        $this->assertContains('Registration ID', $analysis->json('data.detected_columns'));

        $mapping = [
            'full_name' => 'Learner', 'official_matricule' => 'Registration ID',
            'class_name' => 'Imported Level', 'gender' => 'Sex', 'date_of_birth' => 'Birth',
        ];
        $previewFile = UploadedFile::fake()->createWithContent('authorized-export.csv', $csv);
        $preview = $this->postJson('/api/v1/students/imports/preview', [
            'file' => $previewFile, 'column_mapping' => $mapping,
        ])->assertCreated()
            ->assertJsonPath('data.summary.ready', 3)
            ->assertJsonPath('data.summary.error', 0);

        $importId = $preview->json('data.id');
        $classMapping = [
            ['source_class' => 'Form 4', 'class_id' => $form4->id],
            ['source_class' => 'Form 5', 'class_id' => $form5->id],
            ['source_class' => 'Lower Sixth', 'class_id' => $lowerSixth->id],
        ];
        $this->postJson("/api/v1/students/imports/{$importId}/confirm", [
            'class_mapping' => $classMapping, 'duplicate_action' => 'skip',
        ])->assertOk()
            ->assertJsonPath('data.summary.new_students', 3)
            ->assertJsonPath('data.summary.students_enrolled', 3)
            ->assertJsonPath('data.summary.fee_structures_assigned', 3)
            ->assertJsonPath('data.summary.payments_created', 0);

        $this->assertSame(3, Student::query()->whereIn('official_matricule', ['2026001', '2026002', '2026003'])->count());
        $this->assertSame(3, StudentEnrollment::query()->where('academic_year_id', $year->id)->whereIn('class_id', [$form4->id, $form5->id, $lowerSixth->id])->count());
        $this->assertSame(3, Invoice::query()->whereIn('student_id', Student::query()->whereIn('official_matricule', ['2026001', '2026002', '2026003'])->pluck('id'))->count());
        $this->assertSame(0, Payment::query()->count());

        $form5Student = Student::query()->where('official_matricule', '2026002')->firstOrFail();
        $this->assertNull($form5Student->date_of_birth);
        $invoice = Invoice::query()->where('student_id', $form5Student->id)->firstOrFail();
        $this->assertSame(65_000.0, (float) $invoice->total);
        $this->assertSame(0.0, (float) $invoice->paid);
        $this->assertSame(65_000.0, (float) $invoice->balance);

        $payment = $this->postJson("/api/v1/finance/invoices/{$invoice->id}/payments", [
            'paid_at' => now()->toDateString(), 'amount' => 30_000, 'method' => 'cash',
        ])->assertCreated();
        $invoice->refresh();
        $this->assertSame(30_000.0, (float) $invoice->paid);
        $this->assertSame(35_000.0, (float) $invoice->balance);
        $this->get('/api/v1/finance/payments/'.$payment->json('data.id').'/receipt?language=en')
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $repeatFile = UploadedFile::fake()->createWithContent('authorized-export.csv', $csv);
        $repeat = $this->postJson('/api/v1/students/imports/preview', [
            'file' => $repeatFile, 'column_mapping' => $mapping,
        ])->assertCreated()->assertJsonPath('data.summary.existing', 3);
        $this->postJson('/api/v1/students/imports/'.$repeat->json('data.id').'/confirm', [
            'class_mapping' => $classMapping, 'duplicate_action' => 'skip',
        ])->assertOk()->assertJsonPath('data.summary.skipped', 3);
        $this->assertSame(3, Student::query()->whereIn('official_matricule', ['2026001', '2026002', '2026003'])->count());
        $this->assertSame(1, Payment::query()->count());

        $updatedCsv = str_replace('NGONO Divine', 'NGONO Divina', $csv);
        $updateFile = UploadedFile::fake()->createWithContent('authorized-export-update.csv', $updatedCsv);
        $updatePreview = $this->postJson('/api/v1/students/imports/preview', [
            'file' => $updateFile, 'column_mapping' => $mapping,
        ])->assertCreated()->assertJsonPath('data.summary.existing', 3);
        $this->postJson('/api/v1/students/imports/'.$updatePreview->json('data.id').'/confirm', [
            'class_mapping' => $classMapping, 'duplicate_action' => 'update',
        ])->assertOk()->assertJsonPath('data.summary.existing_students_updated', 3);
        $this->assertSame('Divina', Student::query()->where('official_matricule', '2026002')->value('first_name'));
        $this->assertSame(3, Invoice::query()->whereIn('student_id', Student::query()->whereIn('official_matricule', ['2026001', '2026002', '2026003'])->pluck('id'))->count());
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_import_without_fee_configuration_enrolls_with_warning_and_no_invoice(): void
    {
        $this->seed();
        $admin = User::query()->whereHas('role', fn ($query) => $query->where('name', 'administrator'))->firstOrFail();
        $year = AcademicYear::query()->where('school_id', $admin->school_id)->active()->firstOrFail();
        $class = SchoolClass::query()->create([
            'academic_year_id' => $year->id, 'education_system' => 'secondary_general',
            'name' => 'Upper Sixth', 'grade_level' => 'Upper Sixth', 'cycle' => 'second_cycle',
            'language' => 'en', 'capacity' => 40, 'is_active' => true,
        ]);
        Sanctum::actingAs($admin, ['*']);
        $file = UploadedFile::fake()->createWithContent('students.csv', "Matricule,Name,Class\nNOFEE-1,DOE Jane,Upper Sixth");
        $preview = $this->postJson('/api/v1/students/imports/preview', ['file' => $file])->assertCreated();
        $result = $this->postJson('/api/v1/students/imports/'.$preview->json('data.id').'/confirm', [
            'class_mapping' => [['source_class' => 'Upper Sixth', 'class_id' => $class->id]],
            'duplicate_action' => 'skip',
        ])->assertOk()->assertJsonPath('data.summary.students_without_fee_configuration', 1);
        $this->assertStringContainsString('no fee structure', $result->json('data.rows.0.warnings.fee_structure'));
        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_preview_rejects_duplicate_rows_and_invalid_optional_values(): void
    {
        $this->seed();
        $admin = User::query()->whereHas('role', fn ($query) => $query->where('name', 'administrator'))->firstOrFail();
        $year = AcademicYear::query()->where('school_id', $admin->school_id)->active()->firstOrFail();
        SchoolClass::query()->create([
            'academic_year_id' => $year->id, 'education_system' => 'secondary_general',
            'name' => 'Form 4', 'grade_level' => 'Form 4', 'cycle' => 'first_cycle',
            'language' => 'en', 'capacity' => 40, 'is_active' => true,
        ]);
        Sanctum::actingAs($admin, ['*']);

        $file = UploadedFile::fake()->createWithContent('students.csv', implode("\n", [
            'Matricule,Name,Class,Gender,Date of birth',
            'DUP-001,DOE Jane,Form 4,F,2011-01-01',
            'DUP-001,DOE Janet,Form 4,unknown,not-a-date',
        ]));
        $preview = $this->postJson('/api/v1/students/imports/preview', ['file' => $file])
            ->assertCreated()
            ->assertJsonPath('data.summary.ready', 1)
            ->assertJsonPath('data.summary.duplicate_file', 1)
            ->assertJsonPath('data.summary.error', 1);

        $secondRow = collect($preview->json('data.rows'))->firstWhere('row_number', 3);
        $this->assertStringContainsString('Duplicate matricule', $secondRow['errors']['official_matricule']);
        $this->assertStringContainsString('Invalid gender', $secondRow['errors']['gender']);
        $this->assertStringContainsString('Invalid date of birth', $secondRow['errors']['date_of_birth']);
        $this->assertSame(0, Student::query()->where('official_matricule', 'DUP-001')->count());
    }
}
