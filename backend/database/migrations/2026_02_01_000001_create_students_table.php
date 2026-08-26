<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Students — the core student record.
 *
 * `parent_id` and `class_id` are stored as nullable UUIDs without FK
 * constraints; the constraints will be added by their respective phase
 * migrations (Phase 4 for parents, Phase 5 for classes) so each module
 * can be built and tested independently.
 *
 * Source: Database Specification §7 + SRS §4 Student Management.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // ─── Identity ────────────────────────────────────────────
            $table->string('admission_number')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->enum('gender', ['male', 'female']);
            $table->date('date_of_birth');
            $table->string('place_of_birth')->nullable();
            $table->string('nationality')->default('Cameroonian');
            $table->string('religion')->nullable();
            $table->string('photo')->nullable();           // relative path under storage/app/public

            // ─── Contact ─────────────────────────────────────────────
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('Cameroon');

            // ─── Family & enrollment (FKs added later) ───────────────
            $table->uuid('parent_id')->nullable()->index();      // FK in Phase 4
            $table->uuid('class_id')->nullable()->index();       // FK in Phase 5
            $table->foreignUuid('academic_year_id')->nullable()
                ->constrained('academic_years')->nullOnDelete();
            $table->date('enrollment_date');
            $table->string('previous_school')->nullable();

            // ─── Emergency contact ──────────────────────────────────
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relationship')->nullable();

            // ─── Health (optional, sensitive) ───────────────────────
            $table->string('blood_group')->nullable();
            $table->text('allergies')->nullable();
            $table->text('medical_conditions')->nullable();

            // ─── Status ──────────────────────────────────────────────
            $table->enum('status', ['active', 'archived', 'graduated', 'withdrawn'])
                ->default('active');

            // ─── Audit ───────────────────────────────────────────────
            $table->foreignUuid('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // ─── Search & filter indices ─────────────────────────────
            $table->index('status');
            $table->index('gender');
            $table->index('enrollment_date');
            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
