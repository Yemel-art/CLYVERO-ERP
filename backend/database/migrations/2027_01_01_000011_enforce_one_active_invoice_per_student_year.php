<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX invoices_one_open_per_student_year
            ON invoices (student_id, academic_year_id)
            WHERE status <> 'cancelled'
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS invoices_one_open_per_student_year');
    }
};
