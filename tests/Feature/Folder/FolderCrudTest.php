<?php

use App\Models\Book;
use App\Models\Folder;
use App\Models\User;

beforeEach(function () {
    config(['bibliocon.admin_email' => 'admin@bibliocon.test']);
    $this->admin = User::factory()->create(['email' => 'admin@bibliocon.test']);
});

it('creates a folder', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/folders', [
        'name' => 'Manga',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Manga');
    $this->assertDatabaseHas('folders', ['name' => 'Manga', 'created_by' => $this->admin->id]);
});

it('renames a folder', function () {
    $folder = Folder::factory()->create(['created_by' => $this->admin->id, 'name' => 'Old Name']);

    $response = $this->actingAs($this->admin)->patchJson("/api/folders/{$folder->id}", [
        'name' => 'New Name',
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'New Name');
    $this->assertDatabaseHas('folders', ['id' => $folder->id, 'name' => 'New Name']);
});

it('moves a folder to a new parent', function () {
    $parent = Folder::factory()->create(['created_by' => $this->admin->id]);
    $folder = Folder::factory()->create(['created_by' => $this->admin->id, 'parent_id' => null]);

    $response = $this->actingAs($this->admin)->patchJson("/api/folders/{$folder->id}", [
        'parent_id' => $parent->id,
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.parent_id', $parent->id);
});

it('blocks deleting a folder that has subfolders with a 409, not a 500', function () {
    $parent = Folder::factory()->create(['created_by' => $this->admin->id]);
    Folder::factory()->create(['created_by' => $this->admin->id, 'parent_id' => $parent->id]);

    $response = $this->actingAs($this->admin)->deleteJson("/api/folders/{$parent->id}");

    $response->assertStatus(409);
    $this->assertDatabaseHas('folders', ['id' => $parent->id]);
});

it('blocks deleting a folder that has books with a 409, not a 500', function () {
    $folder = Folder::factory()->create(['created_by' => $this->admin->id]);
    Book::factory()->create(['folder_id' => $folder->id]);

    $response = $this->actingAs($this->admin)->deleteJson("/api/folders/{$folder->id}");

    $response->assertStatus(409);
    $this->assertDatabaseHas('folders', ['id' => $folder->id]);
});

it('deletes an empty folder', function () {
    $folder = Folder::factory()->create(['created_by' => $this->admin->id]);

    $response = $this->actingAs($this->admin)->deleteJson("/api/folders/{$folder->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
});

it('lets any authenticated user read folder listings and detail', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['created_by' => $this->admin->id]);

    $this->actingAs($user)->getJson('/api/folders')->assertOk();
    $this->actingAs($user)->getJson("/api/folders/{$folder->id}")->assertOk();
});
