<?php

use App\Jobs\EmbedBookChunks;
use App\Models\Book;
use App\Models\BookChapter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\EpubBuilder;

beforeEach(function () {
    Storage::fake('local');
});

function seedBookWithEpubFile(): Book
{
    $file = EpubBuilder::valid(['title' => 'Embeddable Book']);
    $hash = hash_file('sha256', $file->getRealPath());
    $path = "epubs/{$hash}.epub";

    Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

    $book = Book::factory()->create([
        'file_path' => $path,
        'file_hash' => $hash,
        'embedding_status' => 'processing',
    ]);

    BookChapter::factory()->create([
        'book_id' => $book->id,
        'index' => 0,
        'title' => 'Chapter One',
        'spine_href' => 'chapter1.xhtml',
    ]);

    return $book;
}

it('embeds book chunks and marks the book ready on success', function () {
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [['index' => 0, 'embedding' => array_fill(0, 1536, 0.01)]],
        ], 200),
    ]);

    $book = seedBookWithEpubFile();

    (new EmbedBookChunks($book->id))->handle(app(\App\Services\OpenAiClient::class));

    $book->refresh();
    expect($book->embedding_status)->toBe('ready');
    expect($book->chunks()->count())->toBeGreaterThan(0);
});

it('marks the book failed and leaves no orphaned chunks when OpenAI fails', function () {
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response(['error' => 'boom'], 500),
    ]);

    $book = seedBookWithEpubFile();

    expect(fn () => (new EmbedBookChunks($book->id))->handle(app(\App\Services\OpenAiClient::class)))
        ->toThrow(\RuntimeException::class);

    $book->refresh();
    expect($book->embedding_status)->toBe('failed');
    expect($book->chunks()->count())->toBe(0);
});
