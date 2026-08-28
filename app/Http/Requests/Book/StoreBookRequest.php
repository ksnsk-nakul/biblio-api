<?php

namespace App\Http\Requests\Book;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;
use ZipArchive;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:epub'],
            'folder_id' => ['required', 'integer', 'exists:folders,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var UploadedFile|null $file */
            $file = $this->file('file');

            if (! $file || ! $file->isValid()) {
                return;
            }

            if (! $this->isValidEpub($file->getRealPath())) {
                $validator->errors()->add(
                    'file',
                    'The uploaded file is not a valid EPUB archive (missing META-INF/container.xml).'
                );
            }
        });
    }

    protected function isValidEpub(string $path): bool
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return false;
        }

        $hasContainer = $zip->locateName('META-INF/container.xml') !== false;
        $zip->close();

        return $hasContainer;
    }
}
