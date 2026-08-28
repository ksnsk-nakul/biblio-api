<?php

use App\Models\Book;
use App\Models\BookChunk;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('rejects chat when the book is not ready for embedding', function () {
    $book = Book::factory()->create(['embedding_status' => 'processing']);

    $response = $this->actingAs($this->user)->postJson("/api/books/{$book->id}/chat", [
        'message' => 'What happens in chapter one?',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('embedding_status', 'processing');
});

it('rejects chat with a missing message', function () {
    $book = Book::factory()->create(['embedding_status' => 'ready']);

    $response = $this->actingAs($this->user)->postJson("/api/books/{$book->id}/chat", []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['message']);
});

it('streams a chat response for a ready book, never calling the real OpenAI API', function () {
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [['index' => 0, 'embedding' => array_fill(0, 1536, 0.01)]],
        ], 200),
        'api.openai.com/v1/chat/completions' => Http::response(
            "data: {\"choices\":[{\"delta\":{\"content\":\"Hello\"}}]}\n\ndata: [DONE]\n\n",
            200
        ),
    ]);

    $book = Book::factory()->create(['embedding_status' => 'ready']);
    BookChunk::factory()->create(['book_id' => $book->id, 'content' => 'Some relevant excerpt.']);

    $response = $this->actingAs($this->user)->postJson("/api/books/{$book->id}/chat", [
        'message' => 'Summarize chapter one.',
    ]);

    $response->assertOk();
    $response->assertStreamed();
    expect($response->streamedContent())->toContain('Hello');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'embeddings'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat/completions'));
});
