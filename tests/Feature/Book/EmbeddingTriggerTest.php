<?php

use App\Jobs\EmbedBookChunks;
use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('starts embedding for a book with no prior embedding status', function () {
    Queue::fake();
    $book = Book::factory()->create(['embedding_status' => 'none']);

    $response = $this->actingAs($this->user)->postJson("/api/books/{$book->id}/embed");

    $response->assertStatus(202);
    $response->assertJsonPath('embedding_status', 'processing');
    expect($book->fresh()->embedding_status)->toBe('processing');
    Queue::assertPushed(EmbedBookChunks::class);
});

it('restarts embedding for a book whose embedding previously failed', function () {
    Queue::fake();
    $book = Book::factory()->create(['embedding_status' => 'failed']);

    $response = $this->actingAs($this->user)->postJson("/api/books/{$book->id}/embed");

    $response->assertStatus(202);
    $response->assertJsonPath('embedding_status', 'processing');
    Queue::assertPushed(EmbedBookChunks::class);
});

it('is idempotent: a book already processing returns current status with no new job', function () {
    Queue::fake();
    $book = Book::factory()->create(['embedding_status' => 'processing']);

    $response = $this->actingAs($this->user)->postJson("/api/books/{$book->id}/embed");

    $response->assertStatus(200);
    $response->assertJsonPath('embedding_status', 'processing');
    Queue::assertNothingPushed();
});

it('is idempotent: a book already ready returns current status with no new job', function () {
    Queue::fake();
    $book = Book::factory()->create(['embedding_status' => 'ready']);

    $response = $this->actingAs($this->user)->postJson("/api/books/{$book->id}/embed");

    $response->assertStatus(200);
    $response->assertJsonPath('embedding_status', 'ready');
    Queue::assertNothingPushed();
});
