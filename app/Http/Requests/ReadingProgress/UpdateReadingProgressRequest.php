<?php

namespace App\Http\Requests\ReadingProgress;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReadingProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'chapter_index' => ['required', 'integer', 'min:0'],
            'cfi' => ['required', 'string'],
        ];
    }
}
