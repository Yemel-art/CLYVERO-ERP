<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->uuid('grade_sheet_id')->nullable()->after('id')->index();
        });

        // Bring earlier assessments into the compact sheet view. Assessments
        // with the same class, term and assessment definition form one sheet.
        DB::table('assessments')->orderBy('created_at')->get()
            ->groupBy(fn (object $row): string => implode('|', [
                $row->class_id, $row->term_id, $row->title, $row->type,
                $row->date, $row->max_score, $row->weight,
            ]))
            ->each(function ($rows): void {
                DB::table('assessments')->whereIn('id', $rows->pluck('id'))
                    ->update(['grade_sheet_id' => (string) Str::uuid()]);
            });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropColumn('grade_sheet_id');
        });
    }
};
