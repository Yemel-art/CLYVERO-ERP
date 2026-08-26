<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Permanently removes the discontinued primary-school dataset and default. */
    public function up(): void
    {
        $studentIds = DB::table('students')->where('cycle', 'primary')->pluck('id');

        if ($studentIds->isNotEmpty()) {
            // These relations use RESTRICT constraints, so they must be removed first.
            DB::table('academic_decisions')->whereIn('student_id', $studentIds)->delete();
            DB::table('student_enrollments')->whereIn('student_id', $studentIds)->delete();
            // Finance, grades, attendance, and parent links cascade from students.
            DB::table('students')->whereIn('id', $studentIds)->delete();
        }

        DB::statement("ALTER TABLE students ALTER COLUMN cycle SET DEFAULT 'secondary_general'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE students ALTER COLUMN cycle SET DEFAULT 'secondary_general'");
    }
};
