<?php

use App\Models\Book;
use App\Models\ReadingProgress;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->book = Book::factory()->create();
});

it('creates reading progress for the authenticated user (upsert)', function () {
    $response = $this->actingAs($this->user)->patchJson("/api/books/{$this->book->id}/progress", [
        'chapter_index' => 2,
        'cfi' => 'epubcfi(/6/4!/2/2)',
    ]);

    // First write for this user/book is a create, so the resource reports 201.
    $response->assertCreated();
    $this->assertDatabaseHas('reading_progress', [
        'user_id' => $this->user->id,
        'book_id' => $this->book->id,
        'chapter_index' => 2,
    ]);
});

it('upserts existing progress instead of duplicating', function () {
    $this->actingAs($this->user)->patchJson("/api/books/{$this->book->id}/progress", [
        'chapter_index' => 1,
        'cfi' => 'epubcfi(/6/2!/2/2)',
    ])->assertCreated();

    $this->actingAs($this->user)->patchJson("/api/books/{$this->book->id}/progress", [
        'chapter_index' => 5,
        'cfi' => 'epubcfi(/6/10!/2/2)',
    ])->assertOk();

    $this->assertDatabaseCount('reading_progress', 1);
    $this->assertDatabaseHas('reading_progress', ['chapter_index' => 5]);
});

it('never trusts a client-supplied user_id and scopes progress to the caller', function () {
    $otherUser = User::factory()->create();

    $response = $this->actingAs($this->user)->patchJson("/api/books/{$this->book->id}/progress", [
        'user_id' => $otherUser->id,
        'chapter_index' => 3,
        'cfi' => 'epubcfi(/6/6!/2/2)',
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('reading_progress', [
        'user_id' => $this->user->id,
        'book_id' => $this->book->id,
    ]);
    $this->assertDatabaseMissing('reading_progress', [
        'user_id' => $otherUser->id,
    ]);
});

it('returns only the caller\'s own progress', function () {
    $otherUser = User::factory()->create();

    ReadingProgress::factory()->create([
        'user_id' => $otherUser->id,
        'book_id' => $this->book->id,
        'chapter_index' => 9,
    ]);

    ReadingProgress::factory()->create([
        'user_id' => $this->user->id,
        'book_id' => $this->book->id,
        'chapter_index' => 1,
    ]);

    $response = $this->actingAs($this->user)->getJson("/api/books/{$this->book->id}/progress");

    $response->assertOk();
    $response->assertJsonPath('data.chapter_index', 1);
});

it('returns nulls when the caller has no progress for the book', function () {
    $response = $this->actingAs($this->user)->getJson("/api/books/{$this->book->id}/progress");

    $response->assertOk();
    $response->assertJsonPath('data.chapter_index', null);
});
