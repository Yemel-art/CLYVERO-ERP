<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add cycle (primary / secondary_technical) and speciality
 * (spécialité/filière) to the students table.
 *
 * The school is Primary + Secondary Technical only (no General Secondary).
 * Speciality is only relevant for secondary_technical students.
 *
 * Source: User feedback — student must declare their cycle and speciality
 * because report cards, grading, and subject selection depend on them.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('cycle', 30)->default('secondary_general')
                ->after('previous_school');
            $table->string('speciality', 80)->nullable()
                ->after('cycle');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn(['cycle', 'speciality']);
        });
    }
};
