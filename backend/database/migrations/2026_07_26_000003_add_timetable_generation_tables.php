<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('timetable_configs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_year_id')->unique()->constrained('academic_years')->cascadeOnDelete();
            $table->json('working_days');
            $table->json('periods');
            $table->json('break_periods')->nullable();
            $table->timestamps();
        });

        Schema::create('teacher_availabilities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->enum('day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']);
            $table->unsignedSmallInteger('period_index');
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->unique(['teacher_id', 'day_of_week', 'period_index'], 'teacher_availability_unique');
        });

        Schema::table('class_subject', function (Blueprint $table): void {
            $table->unsignedSmallInteger('weekly_frequency')->default(3);
        });
    }

    public function down(): void
    {
        Schema::table('class_subject', function (Blueprint $table): void {
            $table->dropColumn('weekly_frequency');
        });
        Schema::dropIfExists('teacher_availabilities');
        Schema::dropIfExists('timetable_configs');
    }
};
