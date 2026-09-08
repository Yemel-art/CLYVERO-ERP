<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timetable — weekly schedule of class periods.
 *
 * Each row is one period in the weekly grid for a given class.
 * The same teacher cannot be in two places at once (enforced in the
 * service, not by a DB constraint, because we still want to support
 * temporary substitutions).
 *
 * Source: SRS Timetable Module.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('timetable_slots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignUuid('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->enum('day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']);
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room', 40)->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['class_id', 'day_of_week', 'start_time']);
            $table->index(['teacher_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_slots');
    }
};
