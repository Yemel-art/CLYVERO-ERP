<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teachers — staff records.
 *
 * Linked one-to-one with users (the login account). The teacher row holds
 * employment + qualification metadata; the user row holds auth state.
 *
 * Source: Database Specification §8 + SRS Teacher Management.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // ─── Identity & employment ──────────────────────────────
            $table->foreignUuid('user_id')->nullable()->unique()
                ->constrained('users')->nullOnDelete();
            $table->string('employee_number')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->enum('gender', ['male', 'female']);
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->default('Cameroonian');
            $table->string('photo')->nullable();

            // ─── Contact ─────────────────────────────────────────────
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('Cameroon');

            // ─── Professional ────────────────────────────────────────
            $table->string('qualification')->nullable();        // e.g. "MSc Mathematics"
            $table->string('specialization')->nullable();       // e.g. "Algebra"
            $table->integer('years_of_experience')->default(0);
            $table->date('hire_date');
            $table->decimal('salary', 12, 2)->nullable();       // optional; sensitive

            // ─── Emergency contact ──────────────────────────────────
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();

            // ─── Status ──────────────────────────────────────────────
            $table->enum('status', ['active', 'on_leave', 'archived', 'terminated'])
                ->default('active');

            $table->foreignUuid('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
