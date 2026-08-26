<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carte_scolaire_imports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignUuid('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_filename', 255);
            $table->string('source_hash', 64);
            $table->string('status', 20)->default('draft'); // draft|approved|imported|failed|cancelled
            $table->jsonb('preview_rows');
            $table->jsonb('summary');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status', 'created_at']);
            $table->index(['school_id', 'source_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carte_scolaire_imports');
    }
};
