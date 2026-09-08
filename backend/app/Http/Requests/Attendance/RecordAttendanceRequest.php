<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('attendance.record') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'entries'              => ['required', 'array', 'min:1', 'max:500'],
            'entries.*.student_id' => ['required', 'uuid', 'distinct', 'exists:students,id'],
            'entries.*.status'     => ['required', Rule::enum(AttendanceStatus::class)],
            'entries.*.notes'      => ['nullable', 'string', 'max:300'],
        ];
    }
}
