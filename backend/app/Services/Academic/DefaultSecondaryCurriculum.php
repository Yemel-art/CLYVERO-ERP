<?php

declare(strict_types=1);

namespace App\Services\Academic;

use App\Models\School;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

final class DefaultSecondaryCurriculum
{
    /** @var list<array{code:string,name:string,education_system:string,coefficient:float}> */
    private const SUBJECTS = [
        ['code' => 'MATH', 'name' => 'Mathematics / Mathématiques', 'education_system' => 'both', 'coefficient' => 4.0],
        ['code' => 'ENG', 'name' => 'English Language / Anglais', 'education_system' => 'both', 'coefficient' => 3.0],
        ['code' => 'FRE', 'name' => 'French / Français', 'education_system' => 'both', 'coefficient' => 3.0],
        ['code' => 'PHY', 'name' => 'Physics / Physique', 'education_system' => 'both', 'coefficient' => 3.0],
        ['code' => 'CHEM', 'name' => 'Chemistry / Chimie', 'education_system' => 'both', 'coefficient' => 3.0],
        ['code' => 'BIO', 'name' => 'Biology / Biologie', 'education_system' => 'both', 'coefficient' => 3.0],
        ['code' => 'HIST', 'name' => 'History / Histoire', 'education_system' => 'both', 'coefficient' => 2.0],
        ['code' => 'GEO', 'name' => 'Geography / Géographie', 'education_system' => 'both', 'coefficient' => 2.0],
        ['code' => 'ICT', 'name' => 'Computer Science / Informatique', 'education_system' => 'both', 'coefficient' => 2.0],
        ['code' => 'PHE', 'name' => 'Physical Education / Éducation Physique', 'education_system' => 'both', 'coefficient' => 1.0],
        ['code' => 'CIT', 'name' => 'Citizenship / Éducation à la Citoyenneté', 'education_system' => 'both', 'coefficient' => 1.0],
        ['code' => 'ECON', 'name' => 'Economics / Économie', 'education_system' => 'secondary_general', 'coefficient' => 2.0],
        ['code' => 'LIT', 'name' => 'Literature / Littérature', 'education_system' => 'secondary_general', 'coefficient' => 3.0],
        ['code' => 'PHIL', 'name' => 'Philosophy / Philosophie', 'education_system' => 'secondary_general', 'coefficient' => 2.0],
        ['code' => 'FMATH', 'name' => 'Further Mathematics / Mathématiques Approfondies', 'education_system' => 'secondary_general', 'coefficient' => 4.0],
    ];

    /** @return array{created:int,updated:int,total:int} */
    public function provision(School $school): array
    {
        return DB::transaction(function () use ($school): array {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["default-curriculum:{$school->id}"]);
            $usedColors = Subject::withoutGlobalScopes()
                ->where('school_id', $school->id)
                ->whereNull('deleted_at')
                ->pluck('color')
                ->map(fn ($color) => strtolower((string) $color))
                ->all();
            $created = 0;
            $updated = 0;

            foreach (self::SUBJECTS as $definition) {
                /** @var Subject|null $subject */
                $subject = Subject::withoutGlobalScopes()
                    ->where('school_id', $school->id)
                    ->where(fn ($query) => $query
                        ->whereRaw('UPPER(code) = ?', [$definition['code']])
                        ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($definition['name'])]))
                    ->first();

                if ($subject) {
                    $mergedSystem = $this->mergeEducationSystems((string) $subject->education_system, $definition['education_system']);
                    $changes = ['education_system' => $mergedSystem, 'is_active' => true, 'deleted_at' => null];
                    if ($subject->education_system !== $mergedSystem || ! $subject->is_active || $subject->trashed()) {
                        $subject->forceFill($changes)->save();
                        $updated++;
                    }
                    continue;
                }

                $color = $this->nextUniqueColor($school->id, $definition['code'], $usedColors);
                Subject::withoutGlobalScopes()->create($definition + [
                    'school_id' => $school->id,
                    'color' => $color,
                    'description' => 'Default CLYVERO secondary curriculum; editable by the school administrator.',
                    'is_active' => true,
                    'created_by' => null,
                ]);
                $usedColors[] = strtolower($color);
                $created++;
            }

            return ['created' => $created, 'updated' => $updated, 'total' => count(self::SUBJECTS)];
        }, 3);
    }

    private function mergeEducationSystems(string $current, string $required): string
    {
        if ($current === $required) return $current;
        if ($current === 'both' || $required === 'both') return 'both';

        return 'both';
    }

    /** @param list<string> $usedColors */
    private function nextUniqueColor(string $schoolId, string $code, array $usedColors): string
    {
        foreach (Subject::COLOR_PALETTE as $color) {
            if (! in_array(strtolower($color), $usedColors, true)) return $color;
        }

        for ($attempt = 0; $attempt < 1000; $attempt++) {
            $color = '#'.strtoupper(substr(hash('sha256', "{$schoolId}:{$code}:{$attempt}"), 0, 6));
            if (! in_array(strtolower($color), $usedColors, true)) return $color;
        }

        throw new \RuntimeException('Unable to allocate a unique subject color.');
    }
}
