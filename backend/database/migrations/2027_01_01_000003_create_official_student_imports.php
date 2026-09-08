<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_imports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignUuid('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->string('source', 40)->default('cartes_scolaire');
            $table->string('original_filename');
            $table->enum('status', ['previewed', 'processing', 'completed', 'failed'])->default('previewed');
            $table->jsonb('detected_columns')->nullable();
            $table->jsonb('class_mapping')->nullable();
            $table->jsonb('summary')->nullable();
            $table->jsonb('error_report')->nullable();
            $table->foreignUuid('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'created_at'], 'student_imports_school_date_idx');
        });

        Schema::create('student_import_rows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_import_id')->constrained('student_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('fingerprint', 64);
            $table->jsonb('raw_data');
            $table->jsonb('normalized_data')->nullable();
            $table->enum('status', ['valid', 'duplicate', 'error', 'imported']);
            $table->jsonb('errors')->nullable();
            $table->foreignUuid('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_import_id', 'row_number']);
            $table->index(['student_import_id', 'status']);
            $table->index('fingerprint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_import_rows');
        Schema::dropIfExists('student_imports');
    }
};
