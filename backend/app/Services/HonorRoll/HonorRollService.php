<?php

declare(strict_types=1);

namespace App\Services\HonorRoll;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Services\Grades\GradesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class HonorRollService
{
    public function __construct(private readonly GradesService $grades)
    {
    }

    /** @return array<string, mixed> */
    public function build(AcademicYear $academicYear, Term $term, ?string $classId = null, string $language = 'fr'): array
    {
        $school = $academicYear->school()->firstOrFail();
        $rules = $school->honor_roll_rules ?: [
            ['minimum' => 16, 'max_rank' => null, 'fr' => 'Excellent', 'en' => 'Excellent'],
            ['minimum' => 14, 'max_rank' => null, 'fr' => 'Tableau d’honneur', 'en' => 'Honour Roll'],
            ['minimum' => 12, 'max_rank' => null, 'fr' => 'Très bien', 'en' => 'Very Good'],
            ['minimum' => 10, 'max_rank' => null, 'fr' => 'Encouragement', 'en' => 'Encouragement'],
        ];
        usort($rules, static fn (array $left, array $right): int => (float) $right['minimum'] <=> (float) $left['minimum']);

        $classes = SchoolClass::query()
            ->where('academic_year_id', $academicYear->id)
            ->when($classId, fn ($query) => $query->where('id', $classId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $rows = [];
        foreach ($classes as $class) {
            foreach ($this->grades->classRanking($class, $term) as $ranking) {
                if ($ranking['average'] === null) {
                    continue;
                }
                foreach ($rules as $rule) {
                    $rankAllowed = empty($rule['max_rank']) || $ranking['rank'] <= (int) $rule['max_rank'];
                    if ($ranking['average'] >= (float) $rule['minimum'] && $rankAllowed) {
                        $rows[] = [
                            'class' => $class->name,
                            'student' => $ranking['student'],
                            'average' => $ranking['average'],
                            'rank' => $ranking['rank'],
                            'category' => $rule[$language] ?? $rule['fr'],
                        ];
                        break;
                    }
                }
            }
        }

        return [
            'language' => $language,
            'school' => ['name' => $school->school_name, 'logo_url' => $school->logo ? asset('storage/' . $school->logo) : null],
            'academic_year' => $academicYear->title,
            'term' => $term->name,
            'rows' => $rows,
            'generated_at' => now()->toDateString(),
        ];
    }

    public function download(AcademicYear $academicYear, Term $term, ?string $classId = null, string $language = 'fr'): Response
    {
        return Pdf::loadView('reports.honor-roll', $this->build($academicYear, $term, $classId, $language))
            ->setPaper('a4', 'landscape')
            ->download("honor-roll-{$academicYear->title}.pdf");
    }
}
