<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schools — stores the institution's information.
 * Single school per deployment in Phase 1; table exists for future multi-school.
 * Source: Database Specification §1 "schools".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('school_name');
            $table->string('slogan')->nullable();
            $table->string('logo')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->unique();
            $table->string('address')->nullable();
            $table->string('website')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->uuid('current_academic_year_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
