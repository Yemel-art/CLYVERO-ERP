<?php

declare(strict_types=1);

namespace App\Http\Requests\Timetable;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveGenerationConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('timetable.edit') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'working_days' => ['required', 'array', 'min:1', 'max:6'],
            'working_days.*' => ['required', 'distinct', 'in:monday,tuesday,wednesday,thursday,friday,saturday'],
            'periods' => ['required', 'array', 'min:1', 'max:15'],
            'periods.*.start_time' => ['required', 'date_format:H:i'],
            'periods.*.end_time' => ['required', 'date_format:H:i'],
            'periods.*.label' => ['nullable', 'string', 'max:40'],
            'break_periods' => ['sometimes', 'array'],
            'break_periods.*' => ['integer', 'min:0'],
            'frequencies' => ['sometimes', 'array'],
            'frequencies.*.class_id' => ['required', 'uuid', 'exists:school_classes,id'],
            'frequencies.*.subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'frequencies.*.weekly_frequency' => ['required', 'integer', 'min:1', 'max:15'],
            'availability' => ['sometimes', 'array'],
            'availability.*.teacher_id' => ['required', 'uuid', 'exists:teachers,id'],
            'availability.*.day_of_week' => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday'],
            'availability.*.period_index' => ['required', 'integer', 'min:0'],
            'availability.*.is_available' => ['required', 'boolean'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $periods = $this->input('periods', []);
                $breaks = array_map('intval', $this->input('break_periods', []));
                $seenAvailability = [];

                foreach ($periods as $index => $period) {
                    $start = $period['start_time'] ?? '';
                    $end = $period['end_time'] ?? '';
                    if ($start !== '' && $end !== '' && $start >= $end) {
                        $validator->errors()->add("periods.{$index}.end_time", 'The end time must be after the start time.');
                    }
                    if ($index > 0 && $start < ($periods[$index - 1]['end_time'] ?? '')) {
                        $validator->errors()->add("periods.{$index}.start_time", 'Periods must be ordered and cannot overlap.');
                    }
                }

                foreach ($breaks as $index) {
                    if (! array_key_exists($index, $periods)) {
                        $validator->errors()->add('break_periods', 'A break refers to a period that does not exist.');
                    }
                }

                foreach ([['11:00', '11:30'], ['14:00', '14:30']] as [$start, $end]) {
                    $index = collect($periods)->search(
                        fn (array $period): bool => ($period['start_time'] ?? null) === $start
                            && ($period['end_time'] ?? null) === $end,
                    );
                    if ($index === false || ! in_array((int) $index, $breaks, true)) {
                        $validator->errors()->add(
                            'break_periods',
                            "The fixed school break {$start}–{$end} must be present and marked as a break.",
                        );
                    }
                }

                foreach ($this->input('availability', []) as $index => $row) {
                    if (($row['period_index'] ?? -1) >= count($periods)) {
                        $validator->errors()->add("availability.{$index}.period_index", 'The selected period does not exist.');
                    }
                    $key = implode('|', [
                        $row['teacher_id'] ?? '',
                        $row['day_of_week'] ?? '',
                        $row['period_index'] ?? '',
                    ]);
                    if (isset($seenAvailability[$key])) {
                        $validator->errors()->add("availability.{$index}", 'This teacher period was selected more than once.');
                    }
                    $seenAvailability[$key] = true;
                }
            },
        ];
    }
}
