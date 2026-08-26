<?php

declare(strict_types=1);

namespace App\Services\Timetable;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TimetableConfig;
use App\Models\TimetableSlot;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TimetableService extends BaseService
{
    /** @param array<string, mixed> $attributes */
    public function createSlot(array $attributes): TimetableSlot
    {
        return $this->transaction(function () use ($attributes): TimetableSlot {
            $this->assertOutsideFixedBreaks($attributes['start_time'], $attributes['end_time']);
            $conflict = $this->findTeacherConflict(
                $attributes['teacher_id'] ?? null,
                $attributes['day_of_week'],
                $attributes['start_time'],
                $attributes['end_time'],
            );
            if ($conflict) {
                throw ValidationException::withMessages([
                    'teacher_id' => "This teacher is already teaching {$conflict->class?->name} on {$conflict->day_of_week} from {$conflict->start_time} to {$conflict->end_time}.",
                ]);
            }

            $classConflict = $this->findClassConflict(
                $attributes['class_id'],
                $attributes['day_of_week'],
                $attributes['start_time'],
                $attributes['end_time'],
            );
            if ($classConflict) {
                throw ValidationException::withMessages([
                    'start_time' => "This class already has a lesson from {$classConflict->start_time} to {$classConflict->end_time}.",
                ]);
            }

            $slot = TimetableSlot::create($attributes);
            $this->syncTeachingAssignment($slot->class_id, $slot->subject_id, $slot->teacher_id);

            return $slot;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function updateSlot(TimetableSlot $slot, array $attributes): TimetableSlot
    {
        $teacherId = array_key_exists('teacher_id', $attributes) ? $attributes['teacher_id'] : $slot->teacher_id;
        $day = $attributes['day_of_week'] ?? $slot->day_of_week;
        $start = $attributes['start_time'] ?? $slot->start_time;
        $end = $attributes['end_time'] ?? $slot->end_time;
        $classId = $attributes['class_id'] ?? $slot->class_id;
        $this->assertOutsideFixedBreaks($start, $end);
        $conflict = $this->findTeacherConflict($teacherId, $day, $start, $end, exceptSlotId: $slot->id);
        if ($conflict) {
            throw ValidationException::withMessages([
                'teacher_id' => "This teacher is already teaching {$conflict->class?->name} on {$day} from {$conflict->start_time} to {$conflict->end_time}.",
            ]);
        }
        $classConflict = $this->findClassConflict($classId, $day, $start, $end, exceptSlotId: $slot->id);
        if ($classConflict) {
            throw ValidationException::withMessages([
                'start_time' => "This class already has a lesson from {$classConflict->start_time} to {$classConflict->end_time}.",
            ]);
        }
        $slot->update($attributes);
        $slot->refresh();
        $this->syncTeachingAssignment($slot->class_id, $slot->subject_id, $slot->teacher_id);

        return $slot;
    }

    public function deleteSlot(TimetableSlot $slot): void
    {
        $slot->delete();
    }

    /** @return Collection<int, TimetableSlot> */
    public function forClass(SchoolClass $class): Collection
    {
        return TimetableSlot::with(['subject', 'teacher'])
            ->where('class_id', $class->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    /** @return Collection<int, TimetableSlot> */
    public function forTeacher(string $teacherId): Collection
    {
        return TimetableSlot::with(['subject', 'class'])
            ->where('teacher_id', $teacherId)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    /** @return Collection<int, TimetableSlot> */
    public function forTeacherUser(string $userId): Collection
    {
        $user = \App\Models\User::query()->find($userId);
        $teacherId = $user ? Teacher::resolveForUser($user)?->id : null;

        return $teacherId ? $this->forTeacher($teacherId) : new Collection;
    }

    private function syncTeachingAssignment(?string $classId, ?string $subjectId, ?string $teacherId): void
    {
        if (! $classId || ! $subjectId || ! $teacherId) {
            return;
        }

        $existing = DB::table('class_subject')
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->exists();

        if ($existing) {
            DB::table('class_subject')
                ->where('class_id', $classId)
                ->where('subject_id', $subjectId)
                ->update(['teacher_id' => $teacherId, 'updated_at' => now()]);
            return;
        }

        $subject = Subject::query()->find($subjectId);
        DB::table('class_subject')->insert([
            'id' => (string) Str::uuid(),
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,
            'coefficient' => (float) ($subject?->coefficient ?? 1),
            'weekly_frequency' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return Collection<int, TimetableSlot> */
    public function forSchoolYear(string $academicYearId): Collection
    {
        return TimetableSlot::with(['subject', 'teacher', 'class'])
            ->whereHas('class', fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function saveGenerationConfig(array $attributes): TimetableConfig
    {
        return $this->transaction(function () use ($attributes): TimetableConfig {
            $config = TimetableConfig::updateOrCreate(
                ['academic_year_id' => $attributes['academic_year_id']],
                [
                    'working_days' => $attributes['working_days'],
                    'periods' => $attributes['periods'],
                    'break_periods' => $attributes['break_periods'] ?? [],
                ],
            );

            foreach ($attributes['frequencies'] ?? [] as $frequency) {
                DB::table('class_subject')
                    ->where('class_id', $frequency['class_id'])
                    ->where('subject_id', $frequency['subject_id'])
                    ->update(['weekly_frequency' => $frequency['weekly_frequency']]);
            }

            if (array_key_exists('availability', $attributes)) {
                TeacherAvailability::query()->delete();
                $rows = array_map(static fn (array $availability): array => [
                    'id' => (string) Str::uuid(),
                    ...$availability,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $attributes['availability']);
                if ($rows !== []) {
                    TeacherAvailability::insert($rows);
                }
            }

            return $config->fresh() ?? $config;
        });
    }

    /** @return array<string, mixed> */
    public function generationConfig(string $academicYearId): array
    {
        $config = TimetableConfig::where('academic_year_id', $academicYearId)->first();
        $classes = SchoolClass::with(['subjects', 'subjects.classes'])
            ->where('academic_year_id', $academicYearId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return [
            'config' => $config ? [
                'academic_year_id' => $config->academic_year_id,
                'working_days' => $config->working_days,
                'periods' => $config->periods,
                'break_periods' => $config->break_periods ?? [],
            ] : [
                'academic_year_id' => $academicYearId,
                'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'periods' => [
                    ['label' => 'P1', 'start_time' => '08:00', 'end_time' => '09:00'],
                    ['label' => 'P2', 'start_time' => '09:00', 'end_time' => '10:00'],
                    ['label' => 'P3', 'start_time' => '10:00', 'end_time' => '11:00'],
                    ['label' => 'Break 1', 'start_time' => '11:00', 'end_time' => '11:30'],
                    ['label' => 'P4', 'start_time' => '11:30', 'end_time' => '12:30'],
                    ['label' => 'P5', 'start_time' => '12:30', 'end_time' => '13:30'],
                    ['label' => 'P6', 'start_time' => '13:30', 'end_time' => '14:00'],
                    ['label' => 'Break 2', 'start_time' => '14:00', 'end_time' => '14:30'],
                    ['label' => 'P7', 'start_time' => '14:30', 'end_time' => '15:30'],
                ],
                'break_periods' => [3, 7],
            ],
            'classes' => $classes->map(fn (SchoolClass $class): array => [
                'id' => $class->id,
                'name' => $class->name,
                'subjects' => $class->subjects->map(fn ($subject): array => [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'teacher_id' => $subject->pivot->teacher_id,
                    'weekly_frequency' => (int) $subject->pivot->weekly_frequency,
                ])->values()->all(),
            ])->values()->all(),
            'availability' => TeacherAvailability::query()
                ->whereIn('teacher_id', $classes->flatMap(fn (SchoolClass $class) => $class->subjects->pluck('pivot.teacher_id'))->filter()->unique())
                ->get(['teacher_id', 'day_of_week', 'period_index', 'is_available'])
                ->toArray(),
        ];
    }

    /**
     * Generate a complete, conflict-free timetable. Nothing is written unless
     * every requested weekly subject period can be placed.
     *
     * @return array{generated:bool, slots:int, conflicts:array<int, string>}
     */
    public function generate(string $academicYearId, bool $replaceExisting = false): array
    {
        $config = TimetableConfig::where('academic_year_id', $academicYearId)->first();
        if (! $config) {
            return ['generated' => false, 'slots' => 0, 'conflicts' => ['Configure working days and periods before generating.']];
        }

        $classes = SchoolClass::with('subjects')
            ->where('academic_year_id', $academicYearId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $classIds = $classes->pluck('id');

        if (! $replaceExisting && TimetableSlot::whereIn('class_id', $classIds)->exists()) {
            return ['generated' => false, 'slots' => 0, 'conflicts' => ['A timetable already exists. Confirm replacement to generate again.']];
        }

        $breaks = array_flip($config->break_periods ?? []);
        $periods = [];
        foreach ($config->periods as $periodIndex => $period) {
            if (! isset($breaks[$periodIndex])) {
                $periods[] = ['source_index' => $periodIndex, 'period' => $period];
            }
        }
        $days = $config->working_days;
        if ($days === [] || $periods === []) {
            return ['generated' => false, 'slots' => 0, 'conflicts' => ['At least one working day and one teaching period are required.']];
        }

        $unavailable = TeacherAvailability::where('is_available', false)
            ->get()
            ->keyBy(fn (TeacherAvailability $row): string => "{$row->teacher_id}|{$row->day_of_week}|{$row->period_index}");

        $requirements = [];
        $conflicts = [];
        foreach ($classes as $class) {
            $requested = 0;
            foreach ($class->subjects as $subject) {
                $frequency = max(1, (int) $subject->pivot->weekly_frequency);
                $requested += $frequency;
                if (! $subject->pivot->teacher_id) {
                    $conflicts[] = "{$class->name}: {$subject->name} has no assigned teacher.";

                    continue;
                }
                for ($occurrence = 0; $occurrence < $frequency; $occurrence++) {
                    $requirements[] = [
                        'class_id' => $class->id,
                        'class_name' => $class->name,
                        'subject_id' => $subject->id,
                        'subject_name' => $subject->name,
                        'teacher_id' => $subject->pivot->teacher_id,
                    ];
                }
            }
            if ($requested > count($days) * count($periods)) {
                $conflicts[] = "{$class->name}: {$requested} requested lessons exceed the available weekly periods.";
            }
        }
        if ($conflicts !== []) {
            return ['generated' => false, 'slots' => 0, 'conflicts' => $conflicts];
        }

        usort($requirements, static fn (array $left, array $right): int => $left['teacher_id'] <=> $right['teacher_id']);
        $classBusy = [];
        $teacherBusy = [];
        $subjectDayCount = [];
        $dayLoad = [];
        $generated = [];

        foreach ($requirements as $requirement) {
            $candidates = [];
            foreach ($days as $day) {
                foreach ($periods as $periodPosition => $periodData) {
                    $periodIndex = $periodData['source_index'];
                    $period = $periodData['period'];
                    $slotKey = "{$day}|{$periodIndex}";
                    if (isset($classBusy["{$requirement['class_id']}|{$slotKey}"])
                        || isset($teacherBusy["{$requirement['teacher_id']}|{$slotKey}"])
                        || isset($unavailable["{$requirement['teacher_id']}|{$day}|{$periodIndex}"])) {
                        continue;
                    }
                    $sameSubjectToday = $subjectDayCount["{$requirement['class_id']}|{$requirement['subject_id']}|{$day}"] ?? 0;
                    $currentDayLoad = $dayLoad["{$requirement['class_id']}|{$day}"] ?? 0;
                    $candidates[] = [
                        'day' => $day,
                        'period_index' => $periodIndex,
                        'period' => $period,
                        'score' => ($sameSubjectToday * 100) + ($currentDayLoad * 10) + $periodPosition,
                    ];
                }
            }

            if ($candidates === []) {
                $conflicts[] = "{$requirement['class_name']}: could not place {$requirement['subject_name']} because the teacher or class has no common free period.";

                continue;
            }

            usort($candidates, static fn (array $left, array $right): int => $left['score'] <=> $right['score']);
            $choice = $candidates[0];
            $slotKey = "{$choice['day']}|{$choice['period_index']}";
            $classBusy["{$requirement['class_id']}|{$slotKey}"] = true;
            $teacherBusy["{$requirement['teacher_id']}|{$slotKey}"] = true;
            $subjectDayCount["{$requirement['class_id']}|{$requirement['subject_id']}|{$choice['day']}"] =
                ($subjectDayCount["{$requirement['class_id']}|{$requirement['subject_id']}|{$choice['day']}"] ?? 0) + 1;
            $dayLoad["{$requirement['class_id']}|{$choice['day']}"] =
                ($dayLoad["{$requirement['class_id']}|{$choice['day']}"] ?? 0) + 1;
            $generated[] = [
                'id' => (string) Str::uuid(),
                'class_id' => $requirement['class_id'],
                'subject_id' => $requirement['subject_id'],
                'teacher_id' => $requirement['teacher_id'],
                'day_of_week' => $choice['day'],
                'start_time' => $choice['period']['start_time'],
                'end_time' => $choice['period']['end_time'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($conflicts !== []) {
            return ['generated' => false, 'slots' => 0, 'conflicts' => $conflicts];
        }

        $this->transaction(function () use ($classIds, $generated): void {
            TimetableSlot::whereIn('class_id', $classIds)->delete();
            foreach (array_chunk($generated, 500) as $chunk) {
                TimetableSlot::insert($chunk);
            }
        });

        return ['generated' => true, 'slots' => count($generated), 'conflicts' => []];
    }

    private function findTeacherConflict(
        ?string $teacherId,
        string $day,
        string $start,
        string $end,
        ?string $exceptSlotId = null,
    ): ?TimetableSlot {
        if (! $teacherId) {
            return null;
        }

        return TimetableSlot::query()
            ->with('class:id,name')
            ->where('teacher_id', $teacherId)
            ->where('day_of_week', $day)
            ->when($exceptSlotId, fn ($query) => $query->where('id', '!=', $exceptSlotId))
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->first();
    }

    private function findClassConflict(
        string $classId,
        string $day,
        string $start,
        string $end,
        ?string $exceptSlotId = null,
    ): ?TimetableSlot {
        return TimetableSlot::query()
            ->where('class_id', $classId)
            ->where('day_of_week', $day)
            ->when($exceptSlotId, fn ($query) => $query->where('id', '!=', $exceptSlotId))
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->first();
    }

    private function assertOutsideFixedBreaks(string $start, string $end): void
    {
        foreach ([['11:00', '11:30'], ['14:00', '14:30']] as [$breakStart, $breakEnd]) {
            if ($start < $breakEnd && $end > $breakStart) {
                throw ValidationException::withMessages([
                    'start_time' => "Lessons cannot overlap the fixed school break {$breakStart}–{$breakEnd}.",
                ]);
            }
        }
    }
}
