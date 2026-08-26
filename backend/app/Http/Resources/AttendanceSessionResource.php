<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AttendanceSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AttendanceSession */
class AttendanceSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'class_id'  => $this->class_id,
            'term_id'   => $this->term_id,
            'date'      => $this->date->toDateString(),
            'period'    => $this->period,
            'status'    => $this->status,
            'notes'     => $this->notes,
            'taken_by'  => $this->whenLoaded('takenBy', fn () => $this->takenBy ? [
                'id' => $this->takenBy->id, 'full_name' => $this->takenBy->full_name,
            ] : null),
            'records' => $this->whenLoaded('records', fn () => $this->records->map(fn ($r) => [
                'id'         => $r->id,
                'student_id' => $r->student_id,
                'status'     => $r->status->value,
                'notes'      => $r->notes,
                'student'    => $r->relationLoaded('student') && $r->student ? [
                    'id'                => $r->student->id,
                    'admission_number'  => $r->student->admission_number,
                    'full_name'         => $r->student->full_name,
                    'photo_url'         => $r->student->photo_url,
                ] : null,
            ])->all()),
        ];
    }
}
