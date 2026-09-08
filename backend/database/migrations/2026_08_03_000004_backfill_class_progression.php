<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE school_classes
            SET next_grade_level = CASE
                WHEN LOWER(grade_level) LIKE 'class 1%' THEN 'Class 2'
                WHEN LOWER(grade_level) LIKE 'class 2%' THEN 'Class 3'
                WHEN LOWER(grade_level) LIKE 'class 3%' THEN 'Class 4'
                WHEN LOWER(grade_level) LIKE 'class 4%' THEN 'Class 5'
                WHEN LOWER(grade_level) LIKE 'class 5%' THEN 'Class 6'
                WHEN LOWER(grade_level) LIKE 'class 6%' THEN 'Form 1'
                WHEN LOWER(grade_level) LIKE 'form 1%' THEN 'Form 2'
                WHEN LOWER(grade_level) LIKE 'form 2%' THEN 'Form 3'
                WHEN LOWER(grade_level) LIKE 'form 3%' THEN 'Form 4'
                WHEN LOWER(grade_level) LIKE 'form 4%' THEN 'Form 5'
                WHEN LOWER(grade_level) LIKE 'form 5%' THEN 'Lower Sixth'
                WHEN LOWER(grade_level) LIKE 'lower sixth%' THEN 'Upper Sixth'
                ELSE next_grade_level
            END,
            is_terminal = CASE
                WHEN LOWER(grade_level) LIKE 'upper sixth%' THEN TRUE
                ELSE is_terminal
            END
            WHERE next_grade_level IS NULL OR is_terminal = FALSE
        SQL);
    }

    public function down(): void
    {
        // Existing class progression metadata is safe to retain on rollback.
    }
};
