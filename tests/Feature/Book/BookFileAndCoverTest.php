<?php

use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
});

it('streams the epub file for an authenticated user', function () {
    $book = Book::factory()->create(['file_path' => 'epubs/stream-test.epub']);
    Storage::disk('local')->put($book->file_path, 'fake-epub-bytes');

    $response = $this->actingAs($this->user)->get("/api/books/{$book->id}/file");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/epub+zip');
});

it('returns 401 for the file endpoint when unauthenticated', function () {
    $book = Book::factory()->create(['file_path' => 'epubs/stream-test.epub']);
    Storage::disk('local')->put($book->file_path, 'fake-epub-bytes');

    $this->getJson("/api/books/{$book->id}/file")->assertUnauthorized();
});

it('returns 404 for the cover endpoint when the book has no cover', function () {
    $book = Book::factory()->create(['cover_path' => null]);

    $this->actingAs($this->user)
        ->get("/api/books/{$book->id}/cover")
        ->assertNotFound();
});

it('returns the cover with the correct content type when present', function () {
    $book = Book::factory()->create(['cover_path' => 'covers/test-cover.jpg']);
    Storage::disk('local')->put($book->cover_path, 'fake-jpeg-bytes');

    $response = $this->actingAs($this->user)->get("/api/books/{$book->id}/cover");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/jpeg');
});
