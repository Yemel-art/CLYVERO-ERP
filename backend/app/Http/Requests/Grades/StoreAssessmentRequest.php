<?php

declare(strict_types=1);

namespace App\Http\Requests\Grades;

use App\Models\Teacher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! ($user?->hasPermission('grade.create') ?? false)) {
            return false;
        }
        if (! $user->isTeacher()) {
            return true;
        }

        $teacherId = Teacher::resolveForUser($user)?->id;
        return $teacherId !== null && DB::table('class_subject')
            ->where('class_id', $this->input('class_id'))
            ->where('subject_id', $this->input('subject_id'))
            ->where('teacher_id', $teacherId)
            ->exists();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'term_id'    => ['required', 'uuid', 'exists:terms,id'],
            'class_id'   => ['required', 'uuid', 'exists:school_classes,id'],
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'teacher_id' => ['nullable', 'uuid', 'exists:teachers,id'],
            'title'      => ['required', 'string', 'max:120'],
            'type'       => ['required', 'in:quiz,test,sequence,exam,project,homework,other'],
            'date'       => ['required', 'date'],
            'max_score'  => ['required', 'numeric', 'min:1', 'max:1000'],
            'weight'     => ['required', 'numeric', 'min:0.1', 'max:10'],
        ];
    }
}
