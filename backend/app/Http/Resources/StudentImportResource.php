<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StudentImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StudentImport */
final class StudentImportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $rows = $this->whenLoaded('rows', fn () => $this->rows
            ->sortBy('row_number')
            ->take(500)
            ->map(fn ($row): array => [
                'id' => $row->id,
                'row_number' => $row->row_number,
                'normalized_data' => $row->normalized_data,
                'status' => $row->status,
                'action' => $row->action,
                'errors' => $row->errors,
                'warnings' => $row->warnings,
                'student_id' => $row->student_id,
                'invoice_id' => $row->invoice_id,
                'fee_assigned' => (bool) $row->fee_assigned,
            ])->values());

        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'academic_year' => $this->whenLoaded('academicYear', fn () => [
                'id' => $this->academicYear->id,
                'title' => $this->academicYear->title,
            ]),
            'source' => $this->source,
            'original_filename' => $this->original_filename,
            'status' => $this->status,
            'detected_columns' => $this->detected_columns ?? [],
            'column_mapping' => $this->column_mapping ?? [],
            'class_mapping' => $this->class_mapping ?? [],
            'duplicate_action' => $this->duplicate_action ?? 'skip',
            'summary' => $this->summary ?? [],
            'external_classes' => $this->relationLoaded('rows')
                ? $this->rows->pluck('normalized_data.class_name')->filter()->unique()->sort()->values()
                : [],
            'rows' => $rows,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
