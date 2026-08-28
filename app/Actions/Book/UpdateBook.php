<?php

namespace App\Actions\Book;

use App\Models\Book;

class UpdateBook
{
    public function execute(Book $book, array $data): Book
    {
        $book->fill(array_intersect_key($data, array_flip([
            'title', 'author', 'series_name', 'volume_number', 'folder_id',
        ])))->save();

        return $book->refresh();
    }
}
