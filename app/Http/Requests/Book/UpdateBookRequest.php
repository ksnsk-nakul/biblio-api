<?php

namespace App\Http\Requests\Book;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'author' => ['sometimes', 'string', 'max:255'],
            'series_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'volume_number' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'folder_id' => ['sometimes', 'integer', 'exists:folders,id'],
        ];
    }
}
