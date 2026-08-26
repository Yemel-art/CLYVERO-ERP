<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parents / Guardians table + parent_student pivot.
 *
 * A guardian may have multiple children; a student may have multiple
 * guardians (e.g. mother, father, aunt). The pivot also stores the
 * relationship type and whether the parent is the primary contact.
 *
 * Source: Database Specification §9 + SRS Parent Management.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('parents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->unique()
                ->constrained('users')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->enum('gender', ['male', 'female']);
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('alternate_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('Cameroon');
            $table->string('occupation')->nullable();
            $table->string('workplace')->nullable();
            $table->string('national_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index(['last_name', 'first_name']);
        });

        Schema::create('parent_student', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('parent_id')->constrained('parents')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('relationship');                  // e.g. Father, Mother, Guardian
            $table->boolean('is_primary')->default(false);   // primary contact
            $table->boolean('can_pickup')->default(true);
            $table->timestamps();

            $table->unique(['parent_id', 'student_id']);
        });

        // Now that parents exists, add the FK constraint on students.parent_id
        // (the legacy primary-parent reference for fast lookup).
        Schema::table('students', function (Blueprint $table): void {
            $table->foreign('parent_id')
                ->references('id')->on('parents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropForeign(['parent_id']);
        });
        Schema::dropIfExists('parent_student');
        Schema::dropIfExists('parents');
    }
};
