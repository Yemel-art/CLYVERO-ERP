<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX academic_years_one_active_per_school
            ON academic_years (school_id)
            WHERE status = 'active'
        SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX promotion_policies_one_default_per_school
            ON promotion_policies (school_id)
            WHERE academic_year_id IS NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS promotion_policies_one_default_per_school');
        DB::statement('DROP INDEX IF EXISTS academic_years_one_active_per_school');
    }
};
