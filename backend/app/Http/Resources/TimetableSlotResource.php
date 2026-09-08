<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TimetableSlot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TimetableSlot */
class TimetableSlotResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'class_id'    => $this->class_id,
            'subject_id'  => $this->subject_id,
            'teacher_id'  => $this->teacher_id,
            'day_of_week' => $this->day_of_week,
            'start_time'  => substr((string) $this->start_time, 0, 5),
            'end_time'    => substr((string) $this->end_time, 0, 5),
            'room'        => $this->room,
            'notes'       => $this->notes,
            'subject'     => $this->whenLoaded('subject', fn () => $this->subject ? [
                'id' => $this->subject->id, 'name' => $this->subject->name, 'code' => $this->subject->code, 'color' => $this->subject->color,
            ] : null),
            'teacher'     => $this->whenLoaded('teacher', fn () => $this->teacher ? [
                'id' => $this->teacher->id, 'full_name' => $this->teacher->full_name,
            ] : null),
            'class'       => $this->whenLoaded('class', fn () => $this->class ? [
                'id' => $this->class->id, 'name' => $this->class->name,
            ] : null),
        ];
    }
}
