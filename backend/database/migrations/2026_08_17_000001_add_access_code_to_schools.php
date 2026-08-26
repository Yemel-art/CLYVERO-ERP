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
        Schema::table('schools', function (Blueprint $table): void {
            $table->string('access_code', 16)->nullable()->unique()->after('id');
        });

        DB::table('schools')->whereNull('access_code')->orderBy('id')->each(function (object $school): void {
            do {
                $code = 'SCH-'.strtoupper(substr(bin2hex(random_bytes(6)), 0, 8));
            } while (DB::table('schools')->where('access_code', $code)->exists());

            DB::table('schools')->where('id', $school->id)->update(['access_code' => $code]);
        });

        Schema::table('schools', function (Blueprint $table): void {
            $table->string('access_code', 16)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropUnique(['access_code']);
            $table->dropColumn('access_code');
        });
    }
};
