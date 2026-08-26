<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->string('school_code', 32)->nullable()->after('slug');
            $table->string('secondary_logo')->nullable()->after('logo');
            $table->string('primary_color', 9)->default('#1D4ED8')->after('secondary_logo');
            $table->string('secondary_color', 9)->default('#0F766E')->after('primary_color');
            $table->text('document_header')->nullable()->after('secondary_color');
            $table->text('document_footer')->nullable()->after('document_header');
            $table->string('principal_name')->nullable()->after('document_footer');
            $table->string('principal_title')->nullable()->after('principal_name');
            $table->jsonb('education_systems')->nullable()->after('principal_title');
            $table->boolean('is_active')->default(true)->after('education_systems');
        });

        $schoolIds = DB::table('schools')->orderBy('created_at')->orderBy('id')->pluck('id');
        foreach ($schoolIds as $index => $schoolId) {
            DB::table('schools')->where('id', $schoolId)->update([
                'school_code' => sprintf('CLY-%06d', $index + 1),
                'education_systems' => json_encode(['secondary_general', 'secondary_technical']),
            ]);
        }

        Schema::table('schools', function (Blueprint $table): void {
            $table->string('school_code', 32)->nullable(false)->change();
            $table->unique('school_code', 'schools_school_code_unique');
            $table->index('is_active');
        });
        DB::statement('CREATE UNIQUE INDEX schools_school_code_upper_unique ON schools (UPPER(school_code))');

        // A platform administrator has no fixed school. Every normal school
        // user remains bound to exactly one school by application validation.
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('school_id')->nullable()->change();
        });
        DB::statement('CREATE UNIQUE INDEX users_platform_email_unique ON users (LOWER(email)) WHERE school_id IS NULL AND deleted_at IS NULL');

        Schema::table('students', function (Blueprint $table): void {
            $table->string('official_matricule', 80)->nullable()->after('admission_number');
            $table->unique(['school_id', 'official_matricule'], 'students_school_official_matricule_unique');
            $table->index(['school_id', 'class_id', 'status'], 'students_school_class_status_idx');
        });
        DB::statement('UPDATE students SET official_matricule = admission_number WHERE official_matricule IS NULL');
        DB::statement("UPDATE students SET cycle = 'secondary_general', speciality = NULL WHERE cycle = 'primary'");
        DB::statement("ALTER TABLE students ALTER COLUMN cycle SET DEFAULT 'secondary_general'");
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_secondary_cycle_check CHECK (cycle IN ('secondary_general', 'secondary_technical'))");

        Schema::table('school_classes', function (Blueprint $table): void {
            $table->string('education_system', 40)->nullable()->after('academic_year_id');
        });
        DB::statement("UPDATE school_classes SET education_system = CASE WHEN speciality IS NULL THEN 'secondary_general' ELSE 'secondary_technical' END WHERE education_system IS NULL");
        Schema::table('school_classes', function (Blueprint $table): void {
            $table->string('education_system', 40)->default('secondary_general')->nullable(false)->change();
            $table->index(['academic_year_id', 'education_system'], 'classes_year_education_system_idx');
        });

        Schema::table('subjects', function (Blueprint $table): void {
            $table->string('education_system', 40)->default('both')->after('code');
            $table->index(['school_id', 'education_system', 'is_active'], 'subjects_school_system_active_idx');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->index(['academic_year_id', 'issued_at', 'status'], 'invoices_year_date_status_idx');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->timestamp('voided_at')->nullable()->after('notes');
            $table->foreignUuid('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable()->after('voided_by');
            $table->index(['paid_at', 'voided_at'], 'payments_paid_voided_idx');
            $table->index(['invoice_id', 'voided_at'], 'payments_invoice_voided_idx');
        });

        // Preserve the established 108,000 XAF tuition behaviour for existing
        // customer schools, but move it into editable fee configuration. New
        // schools must explicitly configure their own fees.
        DB::statement(<<<'SQL'
            INSERT INTO fee_structures (
                id, academic_year_id, class_id, name, category, amount,
                frequency, is_required, description, created_at, updated_at
            )
            SELECT gen_random_uuid(), ay.id, NULL, 'Secondary tuition', 'tuition',
                   108000, 'annual', TRUE,
                   'Compatibility fee created during configurable-finance migration.', NOW(), NOW()
              FROM academic_years ay
             WHERE ay.status = 'active'
               AND NOT EXISTS (
                   SELECT 1 FROM fee_structures fs WHERE fs.academic_year_id = ay.id
               )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_platform_email_unique');
        DB::statement('DROP INDEX IF EXISTS schools_school_code_upper_unique');
        DB::statement('ALTER TABLE students DROP CONSTRAINT IF EXISTS students_secondary_cycle_check');
        DB::statement("ALTER TABLE students ALTER COLUMN cycle SET DEFAULT 'primary'");
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_paid_voided_idx');
            $table->dropIndex('payments_invoice_voided_idx');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['voided_at', 'void_reason']);
        });
        Schema::table('invoices', fn (Blueprint $table) => $table->dropIndex('invoices_year_date_status_idx'));
        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropIndex('subjects_school_system_active_idx');
            $table->dropColumn('education_system');
        });
        Schema::table('school_classes', function (Blueprint $table): void {
            $table->dropIndex('classes_year_education_system_idx');
            $table->dropColumn('education_system');
        });
        Schema::table('students', function (Blueprint $table): void {
            $table->dropUnique('students_school_official_matricule_unique');
            $table->dropIndex('students_school_class_status_idx');
            $table->dropColumn('official_matricule');
        });
        Schema::table('users', fn (Blueprint $table) => $table->uuid('school_id')->nullable(false)->change());
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropUnique('schools_school_code_unique');
            $table->dropIndex(['is_active']);
            $table->dropColumn([
                'school_code', 'secondary_logo', 'primary_color', 'secondary_color',
                'document_header', 'document_footer', 'principal_name', 'principal_title',
                'education_systems', 'is_active',
            ]);
        });
    }
};
