<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('schools', 'default_locale')) {
            return;
        }

        DB::statement("ALTER TABLE schools ALTER COLUMN default_locale SET DEFAULT 'fr'");
        DB::table('schools')
            ->where(fn ($query) => $query->whereNull('default_locale')->orWhere('default_locale', 'en'))
            ->update(['default_locale' => 'fr']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('schools', 'default_locale')) {
            DB::statement("ALTER TABLE schools ALTER COLUMN default_locale SET DEFAULT 'en'");
        }
    }
};
