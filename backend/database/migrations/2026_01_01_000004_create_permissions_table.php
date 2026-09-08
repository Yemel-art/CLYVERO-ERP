<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permissions — fine-grained capability flags (e.g. student.create, finance.view).
 * Source: Database Specification §5.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->unique();        // e.g. "student.create"
            $table->string('module');                // e.g. "student"
            $table->string('action');                // e.g. "create"
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
