<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Official spreadsheets frequently omit these optional values. Manual
        // registration can remain stricter while imports preserve unknowns.
        DB::statement('ALTER TABLE students ALTER COLUMN gender DROP NOT NULL');
        DB::statement('ALTER TABLE students ALTER COLUMN date_of_birth DROP NOT NULL');

        Schema::table('student_imports', function (Blueprint $table): void {
            $table->jsonb('column_mapping')->nullable()->after('detected_columns');
            $table->string('duplicate_action', 20)->default('skip')->after('class_mapping');
        });
        Schema::table('student_import_rows', function (Blueprint $table): void {
            $table->string('action', 20)->default('create')->after('status');
            $table->jsonb('warnings')->nullable()->after('errors');
            $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->boolean('fee_assigned')->default(false);
            $table->index(['student_import_id', 'action'], 'student_import_rows_import_action_idx');
        });
    }

    public function down(): void
    {
        Schema::table('student_import_rows', function (Blueprint $table): void {
            $table->dropIndex('student_import_rows_import_action_idx');
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropColumn(['action', 'warnings', 'fee_assigned']);
        });
        Schema::table('student_imports', function (Blueprint $table): void {
            $table->dropColumn(['column_mapping', 'duplicate_action']);
        });

        DB::statement("UPDATE students SET gender = 'male' WHERE gender IS NULL");
        DB::statement("UPDATE students SET date_of_birth = '1900-01-01' WHERE date_of_birth IS NULL");
        DB::statement('ALTER TABLE students ALTER COLUMN gender SET NOT NULL');
        DB::statement('ALTER TABLE students ALTER COLUMN date_of_birth SET NOT NULL');
    }
};
