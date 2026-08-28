<?php

use App\Models\Book;
use App\Models\ReadingProgress;
use App\Models\Shelf;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('returns the caller\'s continue-reading list ordered by most recently updated', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();

    $older = ReadingProgress::factory()->create(['user_id' => $this->user->id, 'book_id' => $bookA->id]);
    $older->forceFill(['updated_at' => now()->subDay()])->save();

    $newer = ReadingProgress::factory()->create(['user_id' => $this->user->id, 'book_id' => $bookB->id]);
    $newer->forceFill(['updated_at' => now()])->save();

    $response = $this->actingAs($this->user)->getJson('/api/dashboard');

    $response->assertOk();
    $ids = collect($response->json('continue_reading'))->pluck('id');
    expect($ids->first())->toBe($bookB->id);
    expect($ids->get(1))->toBe($bookA->id);
});

it('does not include another user\'s reading progress or shelf', function () {
    $otherUser = User::factory()->create();
    $otherBook = Book::factory()->create();
    ReadingProgress::factory()->create(['user_id' => $otherUser->id, 'book_id' => $otherBook->id]);
    Shelf::factory()->create(['user_id' => $otherUser->id, 'book_id' => $otherBook->id]);

    $response = $this->actingAs($this->user)->getJson('/api/dashboard');

    $response->assertOk();
    expect($response->json('continue_reading'))->toBeEmpty();
    expect($response->json('shelf'))->toBeEmpty();
});

it('returns the caller\'s shelf list', function () {
    $book = Book::factory()->create();
    Shelf::factory()->create(['user_id' => $this->user->id, 'book_id' => $book->id]);

    $response = $this->actingAs($this->user)->getJson('/api/dashboard');

    $response->assertOk();
    $ids = collect($response->json('shelf'))->pluck('id');
    expect($ids)->toContain($book->id);
});
