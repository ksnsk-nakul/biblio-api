<?php

namespace App\Actions\Book;

use App\Models\Book;
use Illuminate\Support\Facades\Storage;

class DeleteBook
{
    public function execute(Book $book): void
    {
        $book->delete();

        if ($book->file_path) {
            Storage::disk('local')->delete($book->file_path);
        }

        if ($book->cover_path) {
            Storage::disk('local')->delete($book->cover_path);
        }
    }
}
