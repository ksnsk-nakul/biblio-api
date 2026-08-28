<?php

use App\Models\Book;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->book = Book::factory()->create();
});

it('adds a book to the shelf', function () {
    $response = $this->actingAs($this->user)->postJson("/api/shelf/{$this->book->id}");

    $response->assertNoContent();
    $this->assertDatabaseHas('shelves', ['user_id' => $this->user->id, 'book_id' => $this->book->id]);
});

it('is idempotent: adding the same book twice does not error or duplicate', function () {
    $this->actingAs($this->user)->postJson("/api/shelf/{$this->book->id}")->assertNoContent();
    $this->actingAs($this->user)->postJson("/api/shelf/{$this->book->id}")->assertNoContent();

    $this->assertDatabaseCount('shelves', 1);
});

it('removes a book from the shelf', function () {
    $this->actingAs($this->user)->postJson("/api/shelf/{$this->book->id}")->assertNoContent();

    $response = $this->actingAs($this->user)->deleteJson("/api/shelf/{$this->book->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('shelves', ['user_id' => $this->user->id, 'book_id' => $this->book->id]);
});

it('removing a non-shelved book does not error', function () {
    $response = $this->actingAs($this->user)->deleteJson("/api/shelf/{$this->book->id}");

    $response->assertNoContent();
});
