<?php

namespace App\Http\Requests\Folder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:folders,id',
                Rule::notIn([$this->route('folder')?->id]),
            ],
        ];
    }
}
