<?php

declare(strict_types=1);

namespace App\Services\Academic;

use App\Models\AcademicYear;
use App\Models\Term;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** Creates the standard three-term calendar without overwriting school customizations. */
final class DefaultAcademicTerms
{
    /** @return Collection<int, Term> */
    public function provision(AcademicYear $year): Collection
    {
        return DB::transaction(function () use ($year): Collection {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["academic-terms:{$year->id}"]);
            $locked = AcademicYear::withoutGlobalScopes()->lockForUpdate()->findOrFail($year->id);

            foreach ($this->definitions($locked) as $definition) {
                Term::withoutGlobalScopes()->firstOrCreate(
                    ['academic_year_id' => $locked->id, 'sequence' => $definition['sequence']],
                    $definition,
                );
            }

            $terms = Term::withoutGlobalScopes()
                ->where('academic_year_id', $locked->id)
                ->orderBy('sequence')
                ->get();

            if ($locked->status === AcademicYear::STATUS_ACTIVE && ! $terms->contains('status', Term::STATUS_ACTIVE)) {
                $terms->first()?->update(['status' => Term::STATUS_ACTIVE]);
            }

            return Term::withoutGlobalScopes()
                ->where('academic_year_id', $locked->id)
                ->orderBy('sequence')
                ->get();
        }, 3);
    }

    /** @return list<array{name:string,sequence:int,start_date:string,end_date:string,status:string}> */
    private function definitions(AcademicYear $year): array
    {
        $start = CarbonImmutable::instance($year->start_date);
        $end = CarbonImmutable::instance($year->end_date);
        $followingYear = $end->year;
        $defaultStatus = $year->status === AcademicYear::STATUS_ARCHIVED
            ? Term::STATUS_CLOSED
            : Term::STATUS_UPCOMING;

        $firstEnd = $this->bounded(CarbonImmutable::create($start->year, 12, 31), $start, $end);
        $secondStart = $this->bounded(CarbonImmutable::create($followingYear, 1, 1), $start, $end);
        $secondEnd = $this->bounded(CarbonImmutable::create($followingYear, 3, 31), $secondStart, $end);
        $thirdStart = $this->bounded(CarbonImmutable::create($followingYear, 4, 1), $start, $end);

        return [
            ['name' => 'First Term', 'sequence' => 1, 'start_date' => $start->toDateString(), 'end_date' => $firstEnd->toDateString(), 'status' => $defaultStatus],
            ['name' => 'Second Term', 'sequence' => 2, 'start_date' => $secondStart->toDateString(), 'end_date' => $secondEnd->toDateString(), 'status' => $defaultStatus],
            ['name' => 'Third Term', 'sequence' => 3, 'start_date' => $thirdStart->toDateString(), 'end_date' => $end->toDateString(), 'status' => $defaultStatus],
        ];
    }

    private function bounded(CarbonImmutable $date, CarbonImmutable $minimum, CarbonImmutable $maximum): CarbonImmutable
    {
        if ($date->lessThan($minimum)) return $minimum;
        if ($date->greaterThan($maximum)) return $maximum;

        return $date;
    }
}
