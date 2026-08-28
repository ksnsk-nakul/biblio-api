<?php

use App\Jobs\BulkImportBooks;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['bibliocon.admin_email' => 'admin@bibliocon.test']);
    $this->admin = User::factory()->create(['email' => 'admin@bibliocon.test']);
});

it('rejects a directory path outside the configured import base path', function () {
    config(['bibliocon.import_base_path' => storage_path('app/allowed-base')]);
    @mkdir(storage_path('app/allowed-base'), recursive: true);
    @mkdir(storage_path('app/outside-base'), recursive: true);

    Queue::fake();

    $response = $this->actingAs($this->admin)->postJson('/api/books/bulk-import', [
        'directory' => storage_path('app/outside-base'),
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['directory']);
    Queue::assertNothingPushed();
});

it('accepts a directory path inside the configured import base path', function () {
    $base = storage_path('app/allowed-base');
    @mkdir($base, recursive: true);
    config(['bibliocon.import_base_path' => $base]);

    Queue::fake();

    $response = $this->actingAs($this->admin)->postJson('/api/books/bulk-import', [
        'directory' => $base,
    ]);

    $response->assertStatus(202);
    Queue::assertPushed(BulkImportBooks::class, function (BulkImportBooks $job) use ($base) {
        return $job->directory === $base && $job->importedByUserId === $this->admin->id;
    });
});

it('rejects a directory that does not exist', function () {
    config(['bibliocon.import_base_path' => storage_path('app')]);

    Queue::fake();

    $response = $this->actingAs($this->admin)->postJson('/api/books/bulk-import', [
        'directory' => storage_path('app/does-not-exist-'.uniqid()),
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['directory']);
});
