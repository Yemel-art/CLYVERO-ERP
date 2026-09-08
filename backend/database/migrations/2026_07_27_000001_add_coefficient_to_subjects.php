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
        Schema::table('subjects', function (Blueprint $table): void {
            $table->decimal('coefficient', 4, 2)->default(1.00)->after('code');
        });

        // Preserve the coefficient already in use where possible, then make
        // every existing class assignment consistent with its subject.
        DB::table('subjects')->select('id')->orderBy('id')->each(function (object $subject): void {
            $existing = DB::table('class_subject')
                ->where('subject_id', $subject->id)
                ->orderBy('created_at')
                ->value('coefficient');
            $coefficient = $existing !== null ? (float) $existing : 1.0;
            DB::table('subjects')->where('id', $subject->id)->update(['coefficient' => $coefficient]);
            DB::table('class_subject')->where('subject_id', $subject->id)->update(['coefficient' => $coefficient]);
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropColumn('coefficient');
        });
    }
};
