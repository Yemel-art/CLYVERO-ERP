<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Teacher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class CertificateService
{
    public function employmentCertificate(Teacher $teacher, string $language): Response
    {
        $teacher->loadMissing('school');
        $school = $teacher->school;
        abort_if($school === null, 409, 'The teacher is not linked to a valid school.');
        $yearsOfService = round($teacher->hire_date->diffInMonths(now()) / 12, 1);
        $data = [
            'language' => $language,
            'school' => $this->schoolData($school),
            'teacher' => [
                'full_name' => $teacher->full_name,
                'gender' => $teacher->gender->value,
                'employee_number' => $teacher->employee_number,
                'position' => $teacher->position ?? ($language === 'fr' ? 'Enseignant(e)' : 'Teacher'),
                'department' => $teacher->department ?? $teacher->specialization,
                'status' => $teacher->status->displayName(),
                'hire_date' => $teacher->hire_date->toDateString(),
                'years_of_service' => $yearsOfService,
            ],
            'issued_at' => now()->toDateString(),
        ];

        return Pdf::loadView('reports.employment-certificate', $data)
            ->setPaper('a4')
            ->download("employment-certificate-{$teacher->employee_number}.pdf");
    }

    public function schoolCertificate(Student $student, AcademicYear $academicYear, string $language): Response
    {
        abort_unless($student->school_id === $academicYear->school_id, 404);
        $enrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $academicYear->id)
            ->with('schoolClass')
            ->first();
        $schoolClass = $enrollment?->schoolClass;
        if ($schoolClass === null && $student->academic_year_id === $academicYear->id) {
            $student->load('schoolClass');
            $schoolClass = $student->schoolClass;
        }
        abort_if($schoolClass === null, 422, 'The student was not enrolled in the selected academic year.');
        $school = $academicYear->school()->firstOrFail();
        $data = [
            'language' => $language,
            'school' => $this->schoolData($school),
            'student' => [
                'full_name' => $student->full_name,
                'admission_number' => $student->admission_number,
                'date_of_birth' => $student->date_of_birth->toDateString(),
                'class' => $schoolClass->name,
                'photo_url' => $student->photo_url,
            ],
            'academic_year' => $academicYear->title,
            'issued_at' => now()->toDateString(),
        ];

        return Pdf::loadView('reports.school-certificate', $data)
            ->setPaper('a4')
            ->download("school-certificate-{$student->admission_number}.pdf");
    }

    public function studentIdCard(Student $student): Response
    {
        $student->loadMissing(['school', 'schoolClass', 'academicYear']);
        $school = $student->school;
        $schoolClass = $student->schoolClass;
        $academicYear = $student->academicYear;

        abort_if($school === null, 409, 'The student is not linked to a valid school.');
        abort_if($schoolClass === null, 409, 'Assign the student to a class before generating an ID card.');
        abort_if($academicYear === null, 409, 'Assign the student to an academic year before generating an ID card.');

        $language = in_array($schoolClass->language, ['fr', 'en'], true)
            ? $schoolClass->language
            : $school->default_locale;
        $language = in_array($language, ['fr', 'en'], true) ? $language : 'fr';
        $photoPath = $student->photo && Storage::disk('public')->exists($student->photo)
            ? Storage::disk('public')->path($student->photo)
            : null;

        $data = [
            'language' => $language,
            'school' => $this->schoolData($school),
            'student' => [
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'middle_name' => $student->middle_name,
                'full_name' => $student->full_name,
                'admission_number' => $student->admission_number,
                'official_matricule' => $student->official_matricule,
                'date_of_birth' => $student->date_of_birth?->format('d-m-Y'),
                'place_of_birth' => $student->place_of_birth,
                'age' => $student->age,
                'gender' => $student->gender?->value,
                'class_label' => $schoolClass->identityLabel(),
                'photo_path' => $photoPath,
            ],
            'academic_year' => $academicYear->title,
        ];

        return Pdf::loadView('reports.student-id-card', $data)
            ->setPaper([0, 0, 242.65, 153.07])
            ->download("student-id-card-{$student->admission_number}.pdf");
    }

    /** @return array<string, mixed> */
    private function schoolData(School $school): array
    {
        return [
            'name' => $school->school_name,
            'motto' => $school->slogan,
            'address' => $school->address,
            'phone' => $school->phone,
            'email' => $school->email,
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
              'student_id_card_settings' => $school->studentIdCardSettings(),
              'student_id_card_stamp_url' => $school->student_id_card_stamp && Storage::disk('public')->exists($school->student_id_card_stamp)
                  ? Storage::disk('public')->path($school->student_id_card_stamp)
                  : null,
            'primary_color' => $school->primary_color ?: '#1D4ED8',
            'header' => $school->document_header,
            'footer' => $school->document_footer,
            'principal_name' => $school->principal_name,
            'principal_title' => $school->principal_title,
        ];
    }
}
