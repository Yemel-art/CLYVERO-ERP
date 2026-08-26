<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_classes', function (Blueprint $table): void {
            $table->string('speciality')->nullable()->after('grade_level')->index();
        });
    }

    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table): void {
            $table->dropIndex(['speciality']);
            $table->dropColumn('speciality');
        });
    }
};
