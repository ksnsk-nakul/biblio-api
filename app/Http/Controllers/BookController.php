<?php

namespace App\Http\Controllers;

use App\Actions\Book\DeleteBook;
use App\Actions\Book\IngestEpubBook;
use App\Actions\Book\UpdateBook;
use App\Http\Requests\Book\StoreBookRequest;
use App\Http\Requests\Book\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\Response;

class BookController extends Controller
{
    public function store(StoreBookRequest $request, IngestEpubBook $ingestEpubBook): BookResource
    {
        $book = $ingestEpubBook->execute($request->file('file'), (int) $request->validated('folder_id'));

        return new BookResource($book);
    }

    public function update(UpdateBookRequest $request, Book $book, UpdateBook $updateBook): BookResource
    {
        $book = $updateBook->execute($book, $request->validated());

        return new BookResource($book);
    }

    public function destroy(Book $book, DeleteBook $deleteBook): Response
    {
        $deleteBook->execute($book);

        return response()->noContent();
    }
}
