<?php

use App\Jobs\BulkImportBooks;
use App\Models\Book;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\Support\EpubBuilder;

beforeEach(function () {
    config(['bibliocon.admin_email' => 'admin@bibliocon.test']);
    $this->admin = User::factory()->create(['email' => 'admin@bibliocon.test']);
    $this->user = User::factory()->create();
});

it('blocks a non-admin from creating a folder', function () {
    $this->actingAs($this->user)
        ->postJson('/api/folders', ['name' => 'New Folder'])
        ->assertForbidden();
});

it('allows the admin to create a folder', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/folders', ['name' => 'New Folder'])
        ->assertCreated();
});

it('blocks a non-admin from updating a folder', function () {
    $folder = Folder::factory()->create();

    $this->actingAs($this->user)
        ->patchJson("/api/folders/{$folder->id}", ['name' => 'Renamed'])
        ->assertForbidden();
});

it('blocks a non-admin from deleting a folder', function () {
    $folder = Folder::factory()->create();

    $this->actingAs($this->user)
        ->deleteJson("/api/folders/{$folder->id}")
        ->assertForbidden();
});

it('blocks a non-admin from creating a book', function () {
    $folder = Folder::factory()->create();

    $this->actingAs($this->user)
        ->postJson('/api/books', [
            'file' => EpubBuilder::valid(),
            'folder_id' => $folder->id,
        ])->assertForbidden();
});

it('blocks a non-admin from updating a book', function () {
    $book = Book::factory()->create();

    $this->actingAs($this->user)
        ->patchJson("/api/books/{$book->id}", ['title' => 'New Title'])
        ->assertForbidden();
});

it('blocks a non-admin from deleting a book', function () {
    $book = Book::factory()->create();

    $this->actingAs($this->user)
        ->deleteJson("/api/books/{$book->id}")
        ->assertForbidden();
});

it('blocks a non-admin from bulk-importing books', function () {
    $this->actingAs($this->user)
        ->postJson('/api/books/bulk-import', ['directory' => storage_path('app')])
        ->assertForbidden();
});

it('allows the admin to hit bulk-import (validation still applies)', function () {
    Queue::fake();
    config(['bibliocon.import_base_path' => storage_path('app')]);

    $this->actingAs($this->admin)
        ->postJson('/api/books/bulk-import', ['directory' => storage_path('app')])
        ->assertStatus(202);

    Queue::assertPushed(BulkImportBooks::class);
});

it('allows any authenticated non-admin user to read folders', function () {
    Folder::factory()->create();

    $this->actingAs($this->user)
        ->getJson('/api/folders')
        ->assertOk();
});
