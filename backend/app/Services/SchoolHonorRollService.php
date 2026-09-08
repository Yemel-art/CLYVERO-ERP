<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Services\Grades\GradesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final class SchoolHonorRollService
{
    public function __construct(private readonly GradesService $grades) {}

    /** @return array<string, mixed> */
    public function build(AcademicYear $year, Term $term, ?string $classId = null, string $language = 'fr'): array
    {
        $school = $year->school()->firstOrFail();
        $rules = $school->honor_roll_rules ?: [
            ['minimum' => 16, 'max_rank' => null, 'fr' => 'Excellent', 'en' => 'Excellent'],
            ['minimum' => 14, 'max_rank' => null, 'fr' => 'Tableau d’honneur', 'en' => 'Honour Roll'],
            ['minimum' => 12, 'max_rank' => null, 'fr' => 'Très bien', 'en' => 'Very Good'],
            ['minimum' => 10, 'max_rank' => null, 'fr' => 'Encouragement', 'en' => 'Encouragement'],
        ];
        usort($rules, static fn (array $a, array $b): int => (float) $b['minimum'] <=> (float) $a['minimum']);

        $classes = SchoolClass::query()->where('academic_year_id', $year->id)
            ->when($classId, fn ($query) => $query->where('id', $classId))
            ->where('is_active', true)->orderBy('name')->get();
        $rows = [];
        $summaries = [];

        foreach ($classes as $class) {
            $eligible = 0;
            foreach ($this->grades->classRanking($class, $term) as $ranking) {
                if ($ranking['average'] === null || ! ($ranking['complete'] ?? false)) continue;
                foreach ($rules as $rule) {
                    $rankAllowed = empty($rule['max_rank']) || $ranking['rank'] <= (int) $rule['max_rank'];
                    if ($ranking['average'] >= (float) $rule['minimum'] && $rankAllowed) {
                        $rows[] = [
                            'class' => $class->name, 'student' => $ranking['student'],
                            'average' => $ranking['average'], 'rank' => $ranking['rank'],
                            'category' => $rule[$language] ?? $rule['fr'],
                        ];
                        $eligible++;
                        break;
                    }
                }
            }
            $summaries[] = ['id' => $class->id, 'name' => $class->name, 'eligible_students' => $eligible];
        }

        return [
            'language' => $language,
            'school' => [
                'name' => $school->school_name,
                'logo_url' => $school->logo && Storage::disk('public')->exists($school->logo)
                    ? Storage::disk('public')->path($school->logo)
                    : null,
                'secondary_logo_url' => $school->secondary_logo && Storage::disk('public')->exists($school->secondary_logo)
                    ? Storage::disk('public')->path($school->secondary_logo)
                    : null,
                'header_image_url' => $school->document_header_image && Storage::disk('public')->exists($school->document_header_image)
                    ? Storage::disk('public')->path($school->document_header_image)
                    : null,
                'header_image_settings' => $school->documentHeaderImageSettings(),
                'primary_color' => $school->primary_color ?: '#1D4ED8',
            ],
            'academic_year' => $year->title, 'term' => $term->name,
            'classes' => $summaries, 'rows' => $rows,
            'generated_at' => now()->toDateString(),
        ];
    }

    public function download(AcademicYear $year, Term $term, ?string $classId = null, string $language = 'fr'): Response
    {
        return Pdf::loadView('reports.honor-roll', $this->build($year, $term, $classId, $language))
            ->setPaper('a4', 'landscape')
            ->download("honor-roll-{$year->title}-{$term->name}.pdf");
    }
}
