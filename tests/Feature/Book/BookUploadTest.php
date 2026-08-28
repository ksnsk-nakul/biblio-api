<?php

use App\Models\Book;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Support\EpubBuilder;

beforeEach(function () {
    Storage::fake('local');
    config(['bibliocon.admin_email' => 'admin@bibliocon.test']);
    $this->admin = User::factory()->create(['email' => 'admin@bibliocon.test']);
    $this->folder = Folder::factory()->create(['created_by' => $this->admin->id]);
});

it('ingests a valid epub, creating the book and its chapters', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/books', [
        'file' => EpubBuilder::valid(['title' => 'My Test Book', 'author' => 'Jane Doe']),
        'folder_id' => $this->folder->id,
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.title', 'My Test Book');
    $response->assertJsonPath('data.author', 'Jane Doe');
    $response->assertJsonPath('data.chapter_count', 1);

    $book = Book::first();
    expect($book)->not->toBeNull();
    expect($book->chapters)->toHaveCount(1);
    Storage::disk('local')->assertExists($book->file_path);
});

it('extracts a cover image when present', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/books', [
        'file' => EpubBuilder::valid(['with_cover' => true]),
        'folder_id' => $this->folder->id,
    ]);

    $response->assertCreated();

    $book = Book::first();
    expect($book->cover_path)->not->toBeNull();
    Storage::disk('local')->assertExists($book->cover_path);
});

it('rejects a file with the wrong extension', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/books', [
        'file' => EpubBuilder::wrongExtension(),
        'folder_id' => $this->folder->id,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['file']);
    $this->assertDatabaseCount('books', 0);
});

it('rejects an epub missing META-INF/container.xml', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/books', [
        'file' => EpubBuilder::missingContainer(),
        'folder_id' => $this->folder->id,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['file']);
    $this->assertDatabaseCount('books', 0);
});

it('rejects a duplicate file_hash upload', function () {
    $file1 = EpubBuilder::valid(['seed' => 'same-content']);

    $this->actingAs($this->admin)->postJson('/api/books', [
        'file' => $file1,
        'folder_id' => $this->folder->id,
    ])->assertCreated();

    // Re-upload the exact same bytes by reusing the same underlying temp file path/content.
    $duplicate = new \Illuminate\Http\UploadedFile($file1->getRealPath(), 'duplicate.epub', 'application/epub+zip', null, true);

    $response = $this->actingAs($this->admin)->postJson('/api/books', [
        'file' => $duplicate,
        'folder_id' => $this->folder->id,
    ]);

    $response->assertStatus(409);
    $this->assertDatabaseCount('books', 1);
});

it('requires a valid folder_id', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/books', [
        'file' => EpubBuilder::valid(),
        'folder_id' => 999999,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['folder_id']);
});
