<?php

use App\Actions\Book\RetrieveBookChunks;
use App\Models\Book;
use App\Models\BookChunk;
use Illuminate\Support\Facades\Http;

it('never returns another book\'s chunks', function () {
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [['index' => 0, 'embedding' => array_fill(0, 1536, 0.02)]],
        ], 200),
    ]);

    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();

    BookChunk::factory()->count(3)->create(['book_id' => $bookA->id]);
    BookChunk::factory()->count(3)->create(['book_id' => $bookB->id]);

    $result = app(RetrieveBookChunks::class)->execute($bookA, 'what happens next?', topK: 10);

    expect($result)->not->toBeEmpty();
    expect($result->pluck('book_id')->unique()->all())->toBe([$bookA->id]);

    foreach ($result as $chunk) {
        expect($chunk->book_id)->not->toBe($bookB->id);
    }
});

it('respects the topK limit', function () {
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [['index' => 0, 'embedding' => array_fill(0, 1536, 0.02)]],
        ], 200),
    ]);

    $book = Book::factory()->create();
    BookChunk::factory()->count(5)->create(['book_id' => $book->id]);

    $result = app(RetrieveBookChunks::class)->execute($book, 'query', topK: 2);

    expect($result)->toHaveCount(2);
});
