<?php

namespace App\Actions\Folder;

use App\Models\Book;
use App\Models\Folder;
use Illuminate\Validation\ValidationException;

class DeleteFolder
{
    /**
     * @throws ValidationException if the folder still has children or books.
     */
    public function execute(Folder $folder): void
    {
        if (Folder::where('parent_id', $folder->id)->exists()) {
            throw ValidationException::withMessages([
                'folder' => 'This folder still contains subfolders and cannot be deleted.',
            ])->status(409);
        }

        if (Book::where('folder_id', $folder->id)->exists()) {
            throw ValidationException::withMessages([
                'folder' => 'This folder still contains books and cannot be deleted.',
            ])->status(409);
        }

        $folder->delete();
    }
}
