<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicDecision;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\GradeEntry;
use App\Models\PromotionPolicy;
use App\Models\SchoolClass;
use App\Models\SchoolYearTransition;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Repositories\Contracts\ProgressionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;

class EloquentProgressionRepository implements ProgressionRepositoryInterface
{
    public function policyForYear(string $schoolId, string $academicYearId): ?PromotionPolicy
    {
        return PromotionPolicy::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->where('academic_year_id', $academicYearId)
                ->orWhereNull('academic_year_id'))
            ->orderByRaw('academic_year_id IS NULL')
            ->first();
    }

    public function enrollmentsForYear(string $schoolId, string $academicYearId): Collection
    {
        return StudentEnrollment::query()
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereIn('status', ['active', 'repeating'])
            ->with(['student', 'schoolClass.subjects', 'academicYear.terms'])
            ->orderBy('class_id')
            ->orderBy('student_id')
            ->get();
    }

    public function publishedGrades(StudentEnrollment $enrollment): SupportCollection
    {
        return GradeEntry::query()
            ->where('student_id', $enrollment->student_id)
            ->whereNotNull('score')
            ->whereHas('assessment', fn ($query) => $query
                ->where('class_id', $enrollment->class_id)
                ->where('status', Assessment::STATUS_PUBLISHED)
                ->whereHas('term', fn ($termQuery) => $termQuery
                    ->where('academic_year_id', $enrollment->academic_year_id)))
            ->with(['assessment:id,term_id,class_id,subject_id,max_score,weight'])
            ->get();
    }

    public function saveDecision(StudentEnrollment $enrollment, PromotionPolicy $policy, array $attributes): AcademicDecision
    {
        $existing = AcademicDecision::query()
            ->where('student_id', $enrollment->student_id)
            ->where('academic_year_id', $enrollment->academic_year_id)
            ->first();
        if ($existing?->overridden_at !== null && $policy->allow_class_council_override) {
            $attributes['final_decision'] = $existing->final_decision->value;
        }

        return AcademicDecision::query()->updateOrCreate(
            ['student_id' => $enrollment->student_id, 'academic_year_id' => $enrollment->academic_year_id],
            $attributes + [
                'school_id' => $enrollment->school_id,
                'enrollment_id' => $enrollment->id,
                'promotion_policy_id' => $policy->id,
            ],
        );
    }

    public function decisionsForYear(string $schoolId, string $academicYearId): Collection
    {
        return AcademicDecision::query()
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->with(['student:id,first_name,last_name,admission_number', 'enrollment.schoolClass:id,name'])
            ->orderBy('student_id')
            ->get();
    }

    public function syncEnrollment(Student $student): ?StudentEnrollment
    {
        if (! $student->school_id || ! $student->academic_year_id || ! $student->class_id) {
            return null;
        }

        return StudentEnrollment::query()->updateOrCreate(
            ['student_id' => $student->id, 'academic_year_id' => $student->academic_year_id],
            [
                'school_id' => $student->school_id,
                'class_id' => $student->class_id,
                'enrolled_at' => $student->enrollment_date,
                'status' => 'active',
                'created_by' => $student->created_by,
            ],
        );
    }

    public function cloneAcademicStructure(AcademicYear $fromYear, AcademicYear $toYear): array
    {
        $mapping = [];
        $classes = SchoolClass::query()
            ->where('academic_year_id', $fromYear->id)
            ->where('is_active', true)
            ->with('subjects')
            ->get();

        foreach ($classes as $source) {
            $target = SchoolClass::query()->firstOrCreate(
                ['academic_year_id' => $toYear->id, 'name' => $source->name],
                [
                    'education_system' => $source->education_system,
                    'form_master_id' => $source->form_master_id,
                    'grade_level' => $source->grade_level,
                    'next_grade_level' => $source->next_grade_level,
                    'is_terminal' => $source->is_terminal,
                    'speciality' => $source->speciality,
                    'cycle' => $source->cycle,
                    'language' => $source->language,
                    'capacity' => $source->capacity,
                    'description' => $source->description,
                    'is_active' => true,
                    'created_by' => $source->created_by,
                ],
            );

            $this->syncClassSubjects($source, $target);
            $mapping[$source->id] = $target;
        }

        // A small school may not yet have every next level represented in the
        // closing year. Create the missing destination class so a promoted
        // student is never blocked by structural setup alone.
        foreach ($classes as $source) {
            $nextGrade = $source->next_grade_level ?? SchoolClass::nextLevelFor($source->grade_level);
            if ($nextGrade === null || $source->is_terminal || SchoolClass::isTerminalLevel($source->grade_level)) {
                continue;
            }
            $target = $this->targetClassFor($source, $toYear, false);
            if ($target !== null) {
                continue;
            }

            $name = Str::replaceFirst($source->grade_level, $nextGrade, $source->name);
            if ($name === $source->name) {
                $name = trim($nextGrade.' '.str_replace('_', ' ', (string) $source->speciality));
            }
            $target = SchoolClass::query()->firstOrCreate(
                ['academic_year_id' => $toYear->id, 'name' => $name],
                [
                    'education_system' => $source->education_system,
                    'grade_level' => $nextGrade,
                    'next_grade_level' => SchoolClass::nextLevelFor($nextGrade),
                    'is_terminal' => SchoolClass::isTerminalLevel($nextGrade),
                    'speciality' => $source->speciality,
                    'cycle' => SchoolClass::cycleForLevel($nextGrade, $source->education_system, $source->language),
                    'language' => $source->language,
                    'capacity' => $source->capacity,
                    'description' => $source->description,
                    'is_active' => true,
                    'created_by' => $source->created_by,
                ],
            );
            $this->syncClassSubjects($source, $target);
        }

        return $mapping;
    }

    public function targetClassFor(SchoolClass $sourceClass, AcademicYear $toYear, bool $repeat): ?SchoolClass
    {
        $gradeLevel = $repeat
            ? $sourceClass->grade_level
            : ($sourceClass->next_grade_level ?? SchoolClass::nextLevelFor($sourceClass->grade_level));
        if ($gradeLevel === null) {
            return null;
        }

        return SchoolClass::query()
            ->where('academic_year_id', $toYear->id)
            ->where('education_system', $sourceClass->education_system)
            ->where(fn ($query) => $query
                ->where('grade_level', $gradeLevel)
                ->orWhere('grade_level', 'ilike', $gradeLevel.' (%'))
            ->where('language', $sourceClass->language)
            ->when($sourceClass->speciality, fn ($query) => $query->where('speciality', $sourceClass->speciality), fn ($query) => $query->whereNull('speciality'))
            ->first();
    }

    public function carryEnrollment(StudentEnrollment $source, SchoolClass $target, EnrollmentStatus $sourceStatus): StudentEnrollment
    {
        $source->update(['status' => $sourceStatus->value]);
        $next = StudentEnrollment::query()->updateOrCreate(
            ['student_id' => $source->student_id, 'academic_year_id' => $target->academic_year_id],
            [
                'school_id' => $source->school_id,
                'class_id' => $target->id,
                'enrolled_at' => $target->academicYear->start_date,
                'status' => EnrollmentStatus::Active->value,
                'source_enrollment_id' => $source->id,
                'created_by' => $source->created_by,
            ],
        );
        $source->student->update([
            'class_id' => $target->id,
            'academic_year_id' => $target->academic_year_id,
            'status' => StudentStatus::Active->value,
        ]);

        return $next;
    }

    public function markWithoutEnrollment(StudentEnrollment $source, EnrollmentStatus $status): void
    {
        $source->update(['status' => $status->value]);
        $source->student->update([
            'class_id' => null,
            'status' => $status === EnrollmentStatus::Graduated
                ? StudentStatus::Graduated->value
                : StudentStatus::Excluded->value,
        ]);
    }

    public function startTransition(AcademicYear $fromYear, AcademicYear $toYear, ?string $userId): SchoolYearTransition
    {
        $existing = SchoolYearTransition::query()->where([
            'school_id' => $fromYear->school_id,
            'from_academic_year_id' => $fromYear->id,
            'to_academic_year_id' => $toYear->id,
        ])->first();
        if ($existing?->status === 'completed') {
            return $existing;
        }

        return SchoolYearTransition::query()->updateOrCreate(
            [
                'school_id' => $fromYear->school_id,
                'from_academic_year_id' => $fromYear->id,
                'to_academic_year_id' => $toYear->id,
            ],
            ['status' => 'processing', 'failure_reason' => null, 'triggered_by' => $userId],
        );
    }

    public function completeTransition(SchoolYearTransition $transition, array $summary): SchoolYearTransition
    {
        $transition->update(['status' => 'completed', 'summary' => $summary, 'completed_at' => now()]);

        return $transition->fresh() ?? $transition;
    }

    public function activateAcademicYear(AcademicYear $fromYear, AcademicYear $toYear): void
    {
        $fromYear->update(['status' => AcademicYear::STATUS_ARCHIVED]);
        $toYear->update(['status' => AcademicYear::STATUS_ACTIVE]);
        $toYear->school()->update(['current_academic_year_id' => $toYear->id]);
    }

    public function savePolicy(string $schoolId, ?string $academicYearId, array $attributes, ?string $userId): PromotionPolicy
    {
        return PromotionPolicy::query()->updateOrCreate(
            ['school_id' => $schoolId, 'academic_year_id' => $academicYearId],
            $attributes + ['created_by' => $userId, 'is_active' => true],
        );
    }

    public function decision(string $decisionId): AcademicDecision
    {
        return AcademicDecision::query()->with('promotionPolicy')->findOrFail($decisionId);
    }

    public function overrideDecision(AcademicDecision $decision, array $attributes): AcademicDecision
    {
        $decision->update($attributes);

        return $decision->fresh(['student', 'academicYear', 'promotionPolicy']) ?? $decision;
    }

    private function syncClassSubjects(SchoolClass $source, SchoolClass $target): void
    {
        foreach ($source->subjects as $subject) {
            if ($target->subjects()->whereKey($subject->id)->exists()) {
                continue;
            }
            $target->subjects()->attach($subject->id, [
                'id' => (string) Str::uuid(),
                'teacher_id' => $subject->pivot->teacher_id,
                'coefficient' => $subject->pivot->coefficient,
                'weekly_frequency' => $subject->pivot->weekly_frequency ?? 3,
            ]);
        }
    }
}
