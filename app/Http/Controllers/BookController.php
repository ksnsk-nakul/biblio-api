<?php

namespace App\Http\Controllers;

use App\Actions\Book\DeleteBook;
use App\Actions\Book\IngestEpubBook;
use App\Actions\Book\UpdateBook;
use App\Http\Requests\Book\BulkImportBooksRequest;
use App\Http\Requests\Book\StoreBookRequest;
use App\Http\Requests\Book\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Jobs\BulkImportBooks;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BookController extends Controller
{
    public function index(Request $request)
    {
        $query = Book::query()->with('folder');

        if ($request->filled('folder_id')) {
            $query->where('folder_id', $request->input('folder_id'));
        }

        if ($request->filled('series_name')) {
            $query->where('series_name', $request->input('series_name'));
        }

        return BookResource::collection($query->latest()->paginate(20));
    }

    public function show(Book $book): BookResource
    {
        $book->load(['folder', 'chapters']);

        return new BookResource($book);
    }

    public function file(Book $book): BinaryFileResponse
    {
        return response()->file(Storage::disk('local')->path($book->file_path), [
            'Content-Type' => 'application/epub+zip',
        ]);
    }

    public function store(StoreBookRequest $request, IngestEpubBook $ingestEpubBook): BookResource
    {
        $book = $ingestEpubBook->execute($request->file('file'), (int) $request->validated('folder_id'));

        return new BookResource($book);
    }

    public function search(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        $query = Book::query()->with('folder');

        if ($term !== '') {
            $like = '%'.$term.'%';

            $query->where(function ($q) use ($like) {
                $q->where('title', 'ilike', $like)
                    ->orWhere('author', 'ilike', $like)
                    ->orWhere('series_name', 'ilike', $like);
            });
        }

        return BookResource::collection($query->latest()->paginate(20));
    }

    public function bulkImport(BulkImportBooksRequest $request): \Illuminate\Http\JsonResponse
    {
        BulkImportBooks::dispatch($request->validated('directory'), $request->user()->id);

        return response()->json(['message' => 'Bulk import job dispatched.'], 202);
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
