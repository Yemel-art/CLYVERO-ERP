<?php

declare(strict_types=1);

namespace App\Http\Requests\Grades;

use App\Models\Teacher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RecordGradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! ($user?->hasPermission('grade.record') ?? false)) {
            return false;
        }
        if (! $user->isTeacher()) {
            return true;
        }

        $assessment = $this->route('assessment');
        $teacherId = Teacher::resolveForUser($user)?->id;
        return $teacherId !== null && $assessment !== null && DB::table('class_subject')
            ->where('class_id', $assessment->class_id)
            ->where('subject_id', $assessment->subject_id)
            ->where('teacher_id', $teacherId)
            ->exists();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $assessment = $this->route('assessment');
        $classId = $assessment?->class_id;
        $maxScore = (float) ($assessment?->max_score ?? 20);

        return [
            'entries'                  => ['required', 'array', 'min:1'],
            'entries.*.student_id'     => [
                'required',
                'uuid',
                Rule::exists('students', 'id')
                    ->where('class_id', $classId)
                    ->whereNull('deleted_at'),
            ],
            'entries.*.score'          => ['nullable', 'numeric', 'min:0', "max:{$maxScore}"],
            'entries.*.grade_letter'   => ['nullable', 'string', 'max:4'],
            'entries.*.comment'        => ['nullable', 'string', 'max:300'],
        ];
    }
}
