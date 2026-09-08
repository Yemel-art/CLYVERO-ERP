<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance — sessions and student records.
 *
 * One attendance_session per class per date (per period, optional). The
 * teacher (or admin) opens a session, marks each student, and closes it.
 * Records are immutable once the session is closed.
 *
 * Source: SRS Attendance Module + Business Workflow §5.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignUuid('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignUuid('taken_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date');
            $table->string('period')->nullable();              // optional ("morning"/"afternoon"/period 1..6)
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['class_id', 'date', 'period']);
            $table->index(['class_id', 'date']);
        });

        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('status', ['present', 'absent', 'late', 'excused']);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_sessions');
    }
};
