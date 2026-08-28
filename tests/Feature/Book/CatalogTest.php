<?php

use App\Models\Book;
use App\Models\Folder;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('lists books', function () {
    Book::factory()->count(3)->create();

    $response = $this->actingAs($this->user)->getJson('/api/books');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
});

it('filters by folder_id', function () {
    $folderA = Folder::factory()->create();
    $folderB = Folder::factory()->create();

    Book::factory()->create(['folder_id' => $folderA->id]);
    Book::factory()->create(['folder_id' => $folderB->id]);

    $response = $this->actingAs($this->user)->getJson("/api/books?folder_id={$folderA->id}");

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['folder_id'])->toBe($folderA->id);
});

it('filters by series_name', function () {
    Book::factory()->create(['series_name' => 'Alpha Series']);
    Book::factory()->create(['series_name' => 'Beta Series']);

    $response = $this->actingAs($this->user)->getJson('/api/books?series_name=Alpha Series');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['series_name'])->toBe('Alpha Series');
});

it('searches books by title, author, or series', function () {
    Book::factory()->create(['title' => 'The Great Adventure']);
    Book::factory()->create(['title' => 'Unrelated Book', 'author' => 'Someone']);

    $response = $this->actingAs($this->user)->getJson('/api/search?q=Great');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});
