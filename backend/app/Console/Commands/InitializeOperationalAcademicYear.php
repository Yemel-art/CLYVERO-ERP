<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\School;
use App\Services\Academic\DefaultAcademicTerms;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class InitializeOperationalAcademicYear extends Command
{
    protected $signature = 'clyvero:initialize-2026-2027
        {--school= : Restrict initialization to one school code}
        {--all-schools : Initialize every existing school}
        {--purge-test-students : Permanently delete the explicitly authorized fictitious student dataset}
        {--force : Skip the interactive confirmation}';

    protected $description = 'Initialize 2026-2027 as the first operational year and remove the unused 2025-2026 dataset';

    public function __construct(private readonly DefaultAcademicTerms $defaultTerms)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->option('school') && ! $this->option('all-schools')) {
            $this->error('Specify --school=SCHOOL-CODE or --all-schools.');

            return self::FAILURE;
        }

        $schools = School::withoutGlobalScopes()
            ->when($this->option('school'), fn ($query, $code) => $query->whereRaw('UPPER(school_code) = ?', [strtoupper((string) $code)]))
            ->orderBy('school_name')
            ->get();

        if ($schools->isEmpty()) {
            $this->error('No matching school was found. Nothing was changed.');

            return self::FAILURE;
        }

        $purgeStudents = (bool) $this->option('purge-test-students');
        $warning = $purgeStudents
            ? 'This permanently removes all existing students for the selected school(s), their academic/finance test records, and the unused 2025-2026 year.'
            : 'This removes the unused 2025-2026 academic dataset and activates 2026-2027. Students are retained unless --purge-test-students is supplied.';

        if (! $this->option('force') && ! $this->confirm($warning.' Continue?', false)) {
            $this->warn('Cancelled. Nothing was changed.');

            return self::SUCCESS;
        }

        foreach ($schools as $school) {
            $summary = DB::transaction(fn (): array => $this->initializeSchool((string) $school->id, $purgeStudents), 3);
            $this->info(sprintf(
                '%s (%s): 2026-2027 active; %d test student(s) removed; %d obsolete year record(s) removed.',
                $school->school_name,
                $school->school_code,
                $summary['students'],
                $summary['years'],
            ));

            foreach ($summary['photos'] as $photo) {
                Storage::disk('public')->delete($photo);
            }
        }

        return self::SUCCESS;
    }

    /** @return array{students:int, years:int, photos:list<string>} */
    private function initializeSchool(string $schoolId, bool $purgeStudents): array
    {
        /** @var School $school */
        $school = School::withoutGlobalScopes()->whereKey($schoolId)->lockForUpdate()->firstOrFail();
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["initialize-operational-year:{$schoolId}"]);

        $target = AcademicYear::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('title', '2026-2027')
            ->lockForUpdate()
            ->first();

        if (! $target) {
            $target = AcademicYear::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->whereDate('start_date', '2026-09-01')
                ->whereDate('end_date', '2027-06-30')
                ->lockForUpdate()
                ->first();
        }

        if (! $target) {
            $target = AcademicYear::withoutGlobalScopes()->create([
                'id' => (string) Str::uuid(),
                'school_id' => $schoolId,
                'title' => '2026-2027',
                'start_date' => '2026-09-01',
                'end_date' => '2027-06-30',
                'status' => AcademicYear::STATUS_UPCOMING,
            ]);
        }

        $obsoleteYearIds = AcademicYear::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('id', '<>', $target->id)
            ->whereDate('start_date', '<', '2026-09-01')
            ->pluck('id')
            ->all();

        $photos = [];
        $studentsRemoved = 0;
        if ($purgeStudents) {
            $photos = DB::table('students')->where('school_id', $schoolId)->whereNotNull('photo')->pluck('photo')->all();
            $studentIds = DB::table('students')->where('school_id', $schoolId)->pluck('id')->all();
            if ($studentIds !== []) {
                $this->deleteWhereInIfPresent('academic_decisions', 'student_id', $studentIds);
                $this->deleteWhereInIfPresent('student_enrollments', 'student_id', $studentIds);
                $studentsRemoved = DB::table('students')->whereIn('id', $studentIds)->delete();
            }
        }

        if ($obsoleteYearIds !== []) {
            $this->deleteTransitions($obsoleteYearIds);
            foreach (['academic_decisions', 'student_enrollments', 'official_student_imports', 'carte_scolaire_imports', 'student_imports'] as $table) {
                $this->deleteWhereInIfPresent($table, 'academic_year_id', $obsoleteYearIds);
            }
            $this->deleteWhereInIfPresent('promotion_policies', 'academic_year_id', $obsoleteYearIds);
        }

        AcademicYear::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->whereKeyNot($target->id)
            ->where('status', AcademicYear::STATUS_ACTIVE)
            ->update(['status' => AcademicYear::STATUS_ARCHIVED]);

        $target->forceFill([
            'title' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => AcademicYear::STATUS_ACTIVE,
            'deleted_at' => null,
        ])->save();
        $school->forceFill(['current_academic_year_id' => $target->id])->save();
        $this->defaultTerms->provision($target);

        $yearsRemoved = 0;
        if ($obsoleteYearIds !== []) {
            try {
                $yearsRemoved = DB::table('academic_years')->whereIn('id', $obsoleteYearIds)->delete();
            } catch (\Throwable $exception) {
                throw new RuntimeException('The obsolete academic year still has protected dependent records. The transaction was rolled back: '.$exception->getMessage(), 0, $exception);
            }
        }

        return ['students' => $studentsRemoved, 'years' => $yearsRemoved, 'photos' => array_values($photos)];
    }

    /** @param list<string> $values */
    private function deleteWhereInIfPresent(string $table, string $column, array $values): void
    {
        if ($values !== [] && Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
            DB::table($table)->whereIn($column, $values)->delete();
        }
    }

    /** @param list<string> $yearIds */
    private function deleteTransitions(array $yearIds): void
    {
        if (! Schema::hasTable('school_year_transitions')) {
            return;
        }

        DB::table('school_year_transitions')
            ->whereIn('from_academic_year_id', $yearIds)
            ->orWhereIn('to_academic_year_id', $yearIds)
            ->delete();
    }
}
