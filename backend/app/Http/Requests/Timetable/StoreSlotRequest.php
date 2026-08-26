<?php

declare(strict_types=1);

namespace App\Http\Requests\Timetable;

use Illuminate\Foundation\Http\FormRequest;

class StoreSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('timetable.edit') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'class_id'    => ['required', 'uuid', 'exists:school_classes,id'],
            'subject_id'  => ['nullable', 'uuid', 'exists:subjects,id'],
            'teacher_id'  => ['nullable', 'uuid', 'exists:teachers,id'],
            'day_of_week' => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday'],
            'start_time'  => ['required', 'date_format:H:i'],
            'end_time'    => ['required', 'date_format:H:i', 'after:start_time'],
            'room'        => ['nullable', 'string', 'max:40'],
            'notes'       => ['nullable', 'string', 'max:200'],
        ];
    }
}
