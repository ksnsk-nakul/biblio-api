<?php

namespace App\Http\Controllers;

use App\Actions\Book\DeleteBook;
use App\Actions\Book\IngestEpubBook;
use App\Actions\Book\RetrieveBookChunks;
use App\Actions\Book\TriggerBookEmbedding;
use App\Actions\Book\UpdateBook;
use App\Http\Requests\Book\BulkImportBooksRequest;
use App\Http\Requests\Book\ChatWithBookRequest;
use App\Http\Requests\Book\StoreBookRequest;
use App\Http\Requests\Book\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Jobs\BulkImportBooks;
use App\Models\Book;
use App\Services\OpenAiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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

    public function cover(Book $book): BinaryFileResponse
    {
        abort_if(! $book->cover_path, 404);

        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];

        $extension = strtolower(pathinfo($book->cover_path, PATHINFO_EXTENSION));

        return response()->file(Storage::disk('local')->path($book->cover_path), [
            'Content-Type' => $mimeTypes[$extension] ?? 'application/octet-stream',
            'Cache-Control' => 'public, max-age=86400, immutable',
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

    public function embed(Book $book, TriggerBookEmbedding $triggerBookEmbedding): JsonResponse
    {
        $result = $triggerBookEmbedding->execute($book);

        return response()->json(
            ['embedding_status' => $result['status']],
            $result['triggered'] ? 202 : 200,
        );
    }

    public function chat(
        ChatWithBookRequest $request,
        Book $book,
        RetrieveBookChunks $retrieveBookChunks,
        OpenAiClient $openAi,
    ): JsonResponse|StreamedResponse {
        if ($book->embedding_status !== 'ready') {
            return response()->json([
                'message' => 'This book is not ready for chat yet.',
                'embedding_status' => $book->embedding_status,
            ], 422);
        }

        $message = $request->validated('message');
        $chunks = $retrieveBookChunks->execute($book, $message);

        $context = $chunks->isEmpty()
            ? '(no relevant excerpts found)'
            : $chunks->map(fn ($chunk) => "[Chapter {$chunk->chapter_index}]\n{$chunk->content}")->implode("\n\n---\n\n");

        $systemPrompt = "You are a helpful assistant answering questions about the book \"{$book->title}\" by {$book->author}.\n"
            ."Answer only using the excerpts below. If the excerpts don't contain enough information to answer\n"
            ."confidently, say you don't know rather than guessing or inventing details.\n\n"
            ."Excerpts:\n{$context}";

        return response()->stream(function () use ($openAi, $systemPrompt, $message) {
            try {
                $openAi->streamChat([
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $message],
                ], function (string $delta) {
                    echo 'data: '.json_encode(['delta' => $delta])."\n\n";
                    $this->flushOutput();
                });
            } catch (Throwable $e) {
                Log::error('Book chat stream failed', [
                    'book_id' => $book->id,
                    'error' => $e->getMessage(),
                ]);

                echo 'data: '.json_encode(['error' => 'The chat request failed.'])."\n\n";
                $this->flushOutput();
            }

            echo "data: [DONE]\n\n";
            $this->flushOutput();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    protected function flushOutput(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
