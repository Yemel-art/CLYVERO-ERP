<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class UploadStudentPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');
        return $this->user()?->can('update', $student) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'], // 5 MB max
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'Please choose a photo to upload.',
            'photo.image'    => 'The uploaded file must be an image.',
            'photo.mimes'    => 'The photo must be a JPEG, PNG, or WebP file.',
            'photo.max'      => 'The photo must be smaller than 5 MB.',
        ];
    }
}
