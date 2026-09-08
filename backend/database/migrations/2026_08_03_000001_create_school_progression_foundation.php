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
            $table->string('slug')->nullable()->unique()->after('id');
            $table->string('default_locale', 5)->default('en')->after('country');
        });

        DB::statement('ALTER TABLE academic_years DROP CONSTRAINT IF EXISTS academic_years_status_check');
        DB::statement("ALTER TABLE academic_years ADD CONSTRAINT academic_years_status_check CHECK (status IN ('upcoming', 'active', 'archived'))");
        DB::statement('ALTER TABLE students DROP CONSTRAINT IF EXISTS students_status_check');
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_status_check CHECK (status IN ('active', 'archived', 'graduated', 'withdrawn', 'excluded'))");

        $this->addSchoolOwnership();

        Schema::table('school_classes', function (Blueprint $table): void {
            $table->string('next_grade_level')->nullable()->after('grade_level');
            $table->boolean('is_terminal')->default(false)->after('next_grade_level');
        });

        Schema::create('student_enrollments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignUuid('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignUuid('class_id')->constrained('school_classes')->restrictOnDelete();
            $table->date('enrolled_at');
            $table->enum('status', ['active', 'completed', 'repeating', 'excluded', 'graduated', 'withdrawn'])
                ->default('active');
            // The self-referencing constraint is added after the table is created.
            // PostgreSQL otherwise attempts to create it before the primary-key
            // constraint is visible in the same schema blueprint.
            $table->uuid('source_enrollment_id')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id']);
            $table->index(['school_id', 'academic_year_id', 'class_id', 'status'], 'enrollment_school_year_class_status_idx');
        });

        Schema::table('student_enrollments', function (Blueprint $table): void {
            $table->foreign('source_enrollment_id')
                ->references('id')
                ->on('student_enrollments')
                ->nullOnDelete();
        });

        Schema::create('promotion_policies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignUuid('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('name');
            $table->decimal('grading_scale', 6, 2)->default(20);
            $table->decimal('passing_average', 6, 2)->default(10);
            $table->decimal('minimum_subject_mark', 6, 2)->default(8);
            $table->unsignedSmallInteger('maximum_failed_subjects')->default(2);
            $table->jsonb('critical_subject_ids')->nullable();
            $table->jsonb('failure_conditions')->nullable();
            $table->jsonb('exclusion_conditions')->nullable();
            $table->boolean('allow_class_council_override')->default(true);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'academic_year_id'], 'promotion_policy_school_year_unique');
            $table->index(['school_id', 'is_active']);
        });

        Schema::create('academic_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignUuid('enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
            $table->foreignUuid('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignUuid('promotion_policy_id')->constrained('promotion_policies')->restrictOnDelete();
            $table->enum('computed_decision', ['promoted', 'repeating', 'excluded']);
            $table->enum('final_decision', ['promoted', 'repeating', 'excluded']);
            $table->decimal('final_average', 6, 2)->nullable();
            $table->unsignedSmallInteger('failed_subjects_count')->default(0);
            $table->jsonb('failed_subject_ids')->nullable();
            $table->jsonb('decision_reasons')->nullable();
            $table->text('teacher_appreciation')->nullable();
            $table->text('class_council_recommendation')->nullable();
            $table->text('override_reason')->nullable();
            $table->foreignUuid('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('overridden_at')->nullable();
            $table->timestamp('finalized_at');
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id']);
            $table->index(['school_id', 'academic_year_id', 'final_decision'], 'decision_school_year_status_idx');
        });

        Schema::create('school_year_transitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignUuid('from_academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignUuid('to_academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->jsonb('summary')->nullable();
            $table->text('failure_reason')->nullable();
            $table->foreignUuid('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'from_academic_year_id', 'to_academic_year_id'], 'school_year_transition_unique');
        });

        $this->backfillTenantDataAndEnrollments();
    }

    public function down(): void
    {
        Schema::dropIfExists('school_year_transitions');
        Schema::dropIfExists('academic_decisions');
        Schema::dropIfExists('promotion_policies');
        Schema::dropIfExists('student_enrollments');

        Schema::table('school_classes', function (Blueprint $table): void {
            $table->dropColumn(['next_grade_level', 'is_terminal']);
        });

        foreach (['subjects', 'parents', 'teachers', 'students', 'users'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('school_id');
            });
        }

        Schema::table('schools', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'default_locale']);
        });
    }

    private function addSchoolOwnership(): void
    {
        foreach (['users', 'students', 'teachers', 'parents', 'subjects'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignUuid('school_id')->nullable()->constrained('schools')->restrictOnDelete();
                $table->index('school_id');
            });
        }
    }

    private function backfillTenantDataAndEnrollments(): void
    {
        DB::statement("UPDATE schools SET slug = LOWER(REGEXP_REPLACE(TRIM(school_name), '[^a-zA-Z0-9]+', '-', 'g')) WHERE slug IS NULL");
        DB::statement('UPDATE users SET school_id = (SELECT id FROM schools ORDER BY created_at LIMIT 1) WHERE school_id IS NULL');
        DB::statement('UPDATE students SET school_id = COALESCE((SELECT ay.school_id FROM academic_years ay WHERE ay.id = students.academic_year_id), (SELECT id FROM schools ORDER BY created_at LIMIT 1)) WHERE school_id IS NULL');
        DB::statement('UPDATE teachers SET school_id = (SELECT id FROM schools ORDER BY created_at LIMIT 1) WHERE school_id IS NULL');
        DB::statement('UPDATE parents SET school_id = (SELECT id FROM schools ORDER BY created_at LIMIT 1) WHERE school_id IS NULL');
        DB::statement('UPDATE subjects SET school_id = (SELECT id FROM schools ORDER BY created_at LIMIT 1) WHERE school_id IS NULL');

        DB::statement(<<<'SQL'
            INSERT INTO student_enrollments (
                id, school_id, student_id, academic_year_id, class_id, enrolled_at, status, created_by, created_at, updated_at
            )
            SELECT
                gen_random_uuid(), students.school_id, students.id, students.academic_year_id,
                students.class_id, students.enrollment_date,
                CASE WHEN students.status = 'withdrawn' THEN 'withdrawn' ELSE 'active' END,
                students.created_by, NOW(), NOW()
            FROM students
            WHERE students.school_id IS NOT NULL
              AND students.academic_year_id IS NOT NULL
              AND students.class_id IS NOT NULL
            ON CONFLICT (student_id, academic_year_id) DO NOTHING
        SQL);
    }
};
