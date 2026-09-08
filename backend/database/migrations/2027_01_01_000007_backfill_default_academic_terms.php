<?php

declare(strict_types=1);

use App\Models\AcademicYear;
use App\Services\Academic\DefaultAcademicTerms;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $provisioner = app(DefaultAcademicTerms::class);
        AcademicYear::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->orderBy('start_date')
            ->eachById(static fn (AcademicYear $year) => $provisioner->provision($year));
    }

    public function down(): void
    {
        // Academic terms may already own grades and attendance; rollback must never delete them.
    }
};
