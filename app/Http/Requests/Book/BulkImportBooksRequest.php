<?php

namespace App\Http\Requests\Book;

use Illuminate\Foundation\Http\FormRequest;

class BulkImportBooksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'directory' => ['required', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $directory = $this->input('directory');

            if (! $directory) {
                return;
            }

            if (! is_dir($directory) || ! is_readable($directory)) {
                $validator->errors()->add('directory', 'The given directory does not exist or is not readable.');

                return;
            }

            $realDirectory = realpath($directory);
            $realBasePath = realpath(config('bibliocon.import_base_path'));

            $withinBasePath = $realDirectory !== false
                && $realBasePath !== false
                && ($realDirectory === $realBasePath || str_starts_with($realDirectory, $realBasePath.DIRECTORY_SEPARATOR));

            if (! $withinBasePath) {
                $validator->errors()->add('directory', 'The given directory is outside the allowed import path.');
            }
        });
    }
}
