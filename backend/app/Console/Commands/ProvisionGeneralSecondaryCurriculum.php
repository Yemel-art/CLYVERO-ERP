<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\School;
use App\Services\Academic\DefaultSecondaryCurriculum;
use Illuminate\Console\Command;

final class ProvisionGeneralSecondaryCurriculum extends Command
{
    protected $signature = 'clyvero:provision-general-curriculum
        {--school= : Restrict provisioning to one school code}
        {--all-schools : Provision every school}
        {--force : Skip confirmation}';
    protected $description = 'Provision the editable default secondary-general subject catalogue';

    public function handle(DefaultSecondaryCurriculum $curriculum): int
    {
        if (! $this->option('school') && ! $this->option('all-schools')) {
            $this->error('Specify --school=SCHOOL-CODE or --all-schools.');
            return self::FAILURE;
        }

        $schools = School::query()
            ->when($this->option('school'), fn ($query, $code) => $query->whereRaw('UPPER(school_code) = ?', [strtoupper((string) $code)]))
            ->get();
        if ($schools->isEmpty()) {
            $this->error('No matching school was found.');
            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm('Provision the editable general-secondary subject catalogue?', false)) {
            return self::SUCCESS;
        }

        foreach ($schools as $school) {
            $result = $curriculum->provision($school);
            $this->info("{$school->school_name}: {$result['created']} created, {$result['updated']} updated.");
        }

        return self::SUCCESS;
    }
}
