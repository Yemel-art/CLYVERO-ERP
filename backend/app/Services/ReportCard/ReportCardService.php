<?php

declare(strict_types=1);

namespace App\Services\ReportCard;

use App\Models\Student;
use App\Models\Term;
use App\Models\AcademicDecision;
use App\Models\StudentEnrollment;
use App\Services\Attendance\AttendanceService;
use App\Services\Grades\GradesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Builds bilingual, printable term report cards.
 */
class ReportCardService
{
    public function __construct(
        private readonly GradesService $grades,
        private readonly AttendanceService $attendance,
    ) {
    }

    /** @return array<string, mixed> */
    public function buildData(Student $student, Term $term): array
    {
        $student->load(['parents']);
        $enrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $term->academic_year_id)
            ->with(['schoolClass.academicYear.school', 'schoolClass.formMaster'])
            ->first();
        $reportClass = $enrollment?->schoolClass ?? $student->schoolClass;
        $report = $this->grades->termReportForStudent($student, $term);

        $rank = null;
        $classAverage = null;
        if ($reportClass) {
            $ranking = $this->grades->classRanking($reportClass, $term);
            $averages = array_values(array_filter(
                array_column($ranking, 'average'),
                static fn ($average): bool => $average !== null,
            ));
            if ($averages !== []) {
                $classAverage = round(array_sum($averages) / count($averages), 2);
            }
            foreach ($ranking as $row) {
                if ($row['student']['id'] === $student->id) {
                    $rank = ['position' => $row['rank'], 'total' => count($ranking)];
                    break;
                }
            }
        }

        $attendance = $this->attendance->studentSummary($student->id, $term->id);
        $primary = $student->parents->firstWhere('pivot.is_primary', true) ?? $student->parents->first();
        $language = $reportClass?->language === 'en' ? 'en' : 'fr';
        $school = $reportClass?->academicYear?->school;
        $decision = AcademicDecision::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $term->academic_year_id)
            ->first();

        return [
            'school' => [
                'name' => $school?->school_name ?? config('app.name', 'Clyvero ERP'),
                'motto' => $school?->slogan ?? env('SCHOOL_MOTTO', 'Excellence · Integrity · Service'),
                'logo_url' => $school?->logo && Storage::disk('public')->exists($school->logo)
                    ? Storage::disk('public')->path($school->logo)
                    : null,
                'secondary_logo_url' => $school?->secondary_logo && Storage::disk('public')->exists($school->secondary_logo)
                    ? Storage::disk('public')->path($school->secondary_logo)
                    : null,
                'header_image_url' => $school?->document_header_image && Storage::disk('public')->exists($school->document_header_image)
                    ? Storage::disk('public')->path($school->document_header_image)
                    : null,
                'header_image_settings' => $school?->documentHeaderImageSettings(),
                'primary_color' => $school?->primary_color ?: '#1D4ED8',
                'secondary_color' => $school?->secondary_color ?: '#0F766E',
                'header' => $school?->document_header,
                'footer' => $school?->document_footer,
                'principal_name' => $school?->principal_name,
                'principal_title' => $school?->principal_title,
            ],
            'language' => $language,
            'labels' => $this->labels($language),
            'student' => [
                'full_name' => $student->full_name,
                'admission_number' => $student->admission_number,
                'gender' => ucfirst($student->gender->value),
                'date_of_birth' => $student->date_of_birth?->toDateString(),
                'photo_url' => $student->photo_url,
                'class' => $reportClass?->name,
                'form_master' => $reportClass?->formMaster?->full_name,
                'academic_year' => $reportClass?->academicYear?->title,
                'parent' => $primary?->full_name,
            ],
            'term' => [
                'name' => $term->name,
                'start_date' => $term->start_date->toDateString(),
                'end_date' => $term->end_date->toDateString(),
            ],
            'grades' => $report['subjects'],
            'overall' => [
                'average' => $report['overall_average'],
                'class_average' => $classAverage,
                'total_coefficient' => $report['total_coefficient'],
                'remark' => $this->remarkFor(
                    $report['overall_average'],
                    $language,
                    $school?->report_card_remarks,
                ),
                'rank' => $rank,
            ],
            'attendance' => $attendance,
            'academic_decision' => $decision ? [
                'status' => $decision->final_decision->value,
                'label' => $decision->final_decision->label($language),
                'teacher_appreciation' => $decision->teacher_appreciation,
                'class_council_recommendation' => $decision->class_council_recommendation,
            ] : null,
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    public function downloadPdf(Student $student, Term $term): Response
    {
        $data = $this->buildData($student, $term);
        $pdf = Pdf::loadView('reports.report-card', $data)->setPaper('a4');
        $filename = sprintf(
            'report-card_%s_%s.pdf',
            preg_replace('/\W+/', '-', strtolower($student->full_name)),
            preg_replace('/\W+/', '-', strtolower($term->name)),
        );

        return $pdf->download($filename);
    }

    public function streamPdf(Student $student, Term $term): Response
    {
        $data = $this->buildData($student, $term);
        $pdf = Pdf::loadView('reports.report-card', $data)->setPaper('a4');

        return $pdf->stream("report-{$student->admission_number}.pdf");
    }

    /**
     * @param array<int, array{minimum:numeric, en:string, fr:string}>|null $configured
     */
    private function remarkFor(?float $average, string $language, ?array $configured = null): string
    {
        if ($average === null) {
            return $language === 'en' ? 'No grades available yet.' : 'Aucune note disponible.';
        }

        $remarks = $configured ?: [
            ['minimum' => 16, 'en' => 'Excellent', 'fr' => 'Excellent'],
            ['minimum' => 14, 'en' => 'Very Good', 'fr' => 'Très bien'],
            ['minimum' => 12, 'en' => 'Good', 'fr' => 'Bien'],
            ['minimum' => 10, 'en' => 'Fair', 'fr' => 'Assez bien'],
            ['minimum' => 8, 'en' => 'Average', 'fr' => 'Moyen'],
            ['minimum' => 6, 'en' => 'Needs Improvement', 'fr' => 'Doit s’améliorer'],
            ['minimum' => 0, 'en' => 'Poor', 'fr' => 'Insuffisant'],
        ];

        usort($remarks, static fn (array $left, array $right): int => (float) $right['minimum'] <=> (float) $left['minimum']);
        foreach ($remarks as $remark) {
            if ($average >= (float) $remark['minimum']) {
                return (string) ($remark[$language] ?? $remark['fr'] ?? $remark['en']);
            }
        }

        return $language === 'en' ? 'Poor' : 'Insuffisant';
    }

    /** @return array<string, string> */
    private function labels(string $language): array
    {
        /** @var array<string, string> $labels */
        $labels = trans('report_card', [], $language);

        return $labels;
    }
}
