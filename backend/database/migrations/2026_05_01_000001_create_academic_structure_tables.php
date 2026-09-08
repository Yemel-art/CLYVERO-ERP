<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academic structure — terms, subjects, classes.
 *
 * - `terms` is its own table (per clarification #1) and belongs to an
 *   academic year. The Cameroonian system uses 3 terms per year.
 * - `subjects` are codified offerings (English, Mathematics, ...).
 * - `school_classes` (model: SchoolClass) represent class sections like
 *   "Form 1A". Each class has one form-master teacher.
 * - `class_subject` pivots subjects to classes and records which teacher
 *   teaches that subject in that class.
 *
 * Source: DB Spec §10–§13 + SRS Academic Structure.
 */
return new class extends Migration {
    public function up(): void
    {
        // ─── Terms ──────────────────────────────────────────────────
        Schema::create('terms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('name');                              // "First Term", "Second Term", "Third Term"
            $table->unsignedSmallInteger('sequence');            // 1..3
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['upcoming', 'active', 'closed'])->default('upcoming');
            $table->timestamps();

            $table->unique(['academic_year_id', 'sequence']);
            $table->index(['academic_year_id', 'status']);
        });

        // ─── Subjects ───────────────────────────────────────────────
        Schema::create('subjects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('code', 20)->unique();
            $table->string('color', 9)->default('#2563eb');      // hex incl. #
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });

        // ─── School Classes ─────────────────────────────────────────
        Schema::create('school_classes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignUuid('form_master_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->string('name');                              // e.g. "Form 1A"
            $table->string('grade_level');                       // e.g. "Form 1"
            $table->string('section', 4)->nullable();            // e.g. "A"
            $table->unsignedSmallInteger('capacity')->default(40);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['academic_year_id', 'name']);
            $table->index(['academic_year_id', 'is_active']);
        });

        // ─── Class ↔ Subject (+ teacher per pair) ───────────────────
        Schema::create('class_subject', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->decimal('coefficient', 4, 2)->default(1.00); // grade weighting
            $table->timestamps();

            $table->unique(['class_id', 'subject_id']);
            $table->index('teacher_id');
        });

        // ─── Now add the deferred FK on students.class_id ───────────
        Schema::table('students', function (Blueprint $table): void {
            $table->foreign('class_id')
                ->references('id')->on('school_classes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropForeign(['class_id']);
        });
        Schema::dropIfExists('class_subject');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('terms');
    }
};
