<?php

use App\Models\Book;
use App\Models\BookChapter;
use App\Models\BookChunk;
use App\Models\Folder;
use App\Models\ReadingProgress;
use App\Models\Shelf;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config(['bibliocon.admin_email' => 'admin@bibliocon.test']);
    $this->admin = User::factory()->create(['email' => 'admin@bibliocon.test']);
});

it('updates book metadata', function () {
    $book = Book::factory()->create(['title' => 'Old Title']);

    $response = $this->actingAs($this->admin)->patchJson("/api/books/{$book->id}", [
        'title' => 'New Title',
        'author' => 'New Author',
        'series_name' => 'Some Series',
        'volume_number' => 3,
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.title', 'New Title');
    $this->assertDatabaseHas('books', [
        'id' => $book->id,
        'title' => 'New Title',
        'author' => 'New Author',
        'series_name' => 'Some Series',
        'volume_number' => 3,
    ]);
});

it('deletes a book, cascading chapters, chunks, shelf and progress rows, and removing files', function () {
    $book = Book::factory()->create([
        'file_path' => 'epubs/to-delete.epub',
        'cover_path' => 'covers/to-delete.jpg',
    ]);

    Storage::disk('local')->put($book->file_path, 'epub-bytes');
    Storage::disk('local')->put($book->cover_path, 'cover-bytes');

    BookChapter::factory()->create(['book_id' => $book->id]);
    BookChunk::factory()->create(['book_id' => $book->id]);
    $shelfUser = User::factory()->create();
    Shelf::factory()->create(['book_id' => $book->id, 'user_id' => $shelfUser->id]);
    ReadingProgress::factory()->create(['book_id' => $book->id, 'user_id' => $shelfUser->id]);

    $response = $this->actingAs($this->admin)->deleteJson("/api/books/{$book->id}");

    $response->assertNoContent();

    $this->assertDatabaseMissing('books', ['id' => $book->id]);
    $this->assertDatabaseMissing('book_chapters', ['book_id' => $book->id]);
    $this->assertDatabaseMissing('book_chunks', ['book_id' => $book->id]);
    $this->assertDatabaseMissing('shelves', ['book_id' => $book->id]);
    $this->assertDatabaseMissing('reading_progress', ['book_id' => $book->id]);

    Storage::disk('local')->assertMissing($book->file_path);
    Storage::disk('local')->assertMissing($book->cover_path);
});
