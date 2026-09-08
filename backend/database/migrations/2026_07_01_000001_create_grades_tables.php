<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grades — assessments and grade_entries.
 *
 * An `assessment` is a graded event in a (class, subject, term) triple:
 * a quiz, test, mid-term, end-of-term, etc. Each has a max_score and a
 * `weight` within the subject (so multiple assessments combine into one
 * subject grade). One grade_entry per student per assessment.
 *
 * Subject coefficient (from class_subject pivot) is applied a layer
 * higher when computing the overall term average.
 *
 * Source: SRS Grades & Assessment Module + Business Workflow §6.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('term_id')->constrained('terms')->cascadeOnDelete();
            $table->foreignUuid('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->string('title');                                 // e.g. "First Sequence Test"
            $table->enum('type', ['quiz', 'test', 'sequence', 'exam', 'project', 'homework', 'other'])->default('test');
            $table->date('date');
            $table->decimal('max_score', 6, 2)->default(20.00);      // /20 default (Cameroonian system)
            $table->decimal('weight', 5, 2)->default(1.00);          // weight within the subject
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['class_id', 'subject_id', 'term_id']);
            $table->index('status');
        });

        Schema::create('grade_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('score', 6, 2)->nullable();   // null = not graded yet
            $table->string('grade_letter', 4)->nullable(); // A, B+, etc.
            $table->text('comment')->nullable();
            $table->foreignUuid('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['assessment_id', 'student_id']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_entries');
        Schema::dropIfExists('assessments');
    }
};
