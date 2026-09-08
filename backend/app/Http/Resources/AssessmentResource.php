<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Assessment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Assessment */
class AssessmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'term_id'    => $this->term_id,
            'class_id'   => $this->class_id,
            'subject_id' => $this->subject_id,
            'teacher_id' => $this->teacher_id,
            'title'      => $this->title,
            'type'       => $this->type,
            'date'       => $this->date->toDateString(),
            'max_score'  => (float) $this->max_score,
            'weight'     => (float) $this->weight,
            'status'     => $this->status,

            'subject' => $this->whenLoaded('subject', fn () => [
                'id' => $this->subject->id, 'name' => $this->subject->name, 'code' => $this->subject->code, 'color' => $this->subject->color,
            ]),
            'term' => $this->whenLoaded('term', fn () => [
                'id' => $this->term->id, 'name' => $this->term->name,
            ]),
            'entries' => $this->whenLoaded('entries', fn () => $this->entries->map(fn ($e) => [
                'id'           => $e->id,
                'student_id'   => $e->student_id,
                'score'        => $e->score !== null ? (float) $e->score : null,
                'grade_letter' => $e->grade_letter,
                'comment'      => $e->comment,
                'graded_at'    => $e->graded_at?->toIso8601String(),
                'student'      => $e->relationLoaded('student') && $e->student ? [
                    'id'                => $e->student->id,
                    'admission_number'  => $e->student->admission_number,
                    'full_name'         => $e->student->full_name,
                    'photo_url'         => $e->student->photo_url,
                ] : null,
            ])->all()),
        ];
    }
}
