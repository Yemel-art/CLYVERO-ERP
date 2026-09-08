<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class OpenSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('attendance.record') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'class_id' => ['required', 'uuid', 'exists:school_classes,id'],
            'date'     => ['required', 'date'],
            'period'   => ['nullable', 'string', 'max:30'],
        ];
    }
}
