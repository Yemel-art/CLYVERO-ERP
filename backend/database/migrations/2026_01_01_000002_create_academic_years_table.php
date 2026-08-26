<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academic Years — one active at a time, others archived (read-only).
 * Source: Database Specification §2 + Business Workflow Global Rules.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('title');                         // e.g. "2025–2026"
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'archived'])->default('archived');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'title']);
            $table->index('status');
        });

        // Now that academic_years exists, wire the FK from schools.current_academic_year_id
        Schema::table('schools', function (Blueprint $table): void {
            $table->foreign('current_academic_year_id')
                ->references('id')->on('academic_years')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropForeign(['current_academic_year_id']);
        });
        Schema::dropIfExists('academic_years');
    }
};
