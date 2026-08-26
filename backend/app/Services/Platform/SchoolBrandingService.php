<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\School;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class SchoolBrandingService
{
    /** @param array<string, mixed> $attributes */
    public function update(School $school, array $attributes): School
    {
        return DB::transaction(function () use ($school, $attributes): School {
            $school->fill($attributes)->save();

            return $school->fresh(['currentAcademicYear']) ?? $school;
        });
    }

    public function uploadLogo(School $school, UploadedFile $file, string $kind): School
    {
        $column = match ($kind) {
            'secondary' => 'secondary_logo',
            'document_header' => 'document_header_image',
            default => 'logo',
        };
        $oldPath = $school->getAttribute($column);
        $extension = $file->guessExtension() ?: 'png';
        $newPath = $file->storeAs(
            'schools',
            $school->id.'-'.$kind.'-'.Str::random(10).'.'.$extension,
            'public',
        );

        try {
            $school->update([$column => $newPath]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($newPath);
            throw $exception;
        }

        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return $school->fresh(['currentAcademicYear']) ?? $school;
    }
}
