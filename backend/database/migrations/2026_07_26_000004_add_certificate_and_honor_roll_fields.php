<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table): void {
            $table->string('position')->nullable();
            $table->string('department')->nullable();
        });
        Schema::table('schools', function (Blueprint $table): void {
            $table->json('honor_roll_rules')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table): void {
            $table->dropColumn(['position', 'department']);
        });
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropColumn('honor_roll_rules');
        });
    }
};
