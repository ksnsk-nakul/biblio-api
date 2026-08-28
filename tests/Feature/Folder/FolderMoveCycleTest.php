<?php

use App\Models\Folder;
use App\Models\User;

beforeEach(function () {
    config(['bibliocon.admin_email' => 'admin@bibliocon.test']);
    $this->admin = User::factory()->create(['email' => 'admin@bibliocon.test']);
});

it('rejects setting a folder as its own parent with a clean 422', function () {
    $folder = Folder::factory()->create(['created_by' => $this->admin->id]);

    $response = $this->actingAs($this->admin)->patchJson("/api/folders/{$folder->id}", [
        'parent_id' => $folder->id,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['parent_id']);
});

it('rejects moving a folder into its own direct child with a clean 422', function () {
    $parent = Folder::factory()->create(['created_by' => $this->admin->id]);
    $child = Folder::factory()->create(['created_by' => $this->admin->id, 'parent_id' => $parent->id]);

    $response = $this->actingAs($this->admin)->patchJson("/api/folders/{$parent->id}", [
        'parent_id' => $child->id,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['parent_id']);
    $this->assertDatabaseHas('folders', ['id' => $parent->id, 'parent_id' => null]);
});

it('rejects moving a folder into a deep descendant with a clean 422 (no crash / no infinite loop)', function () {
    $grandparent = Folder::factory()->create(['created_by' => $this->admin->id]);
    $parent = Folder::factory()->create(['created_by' => $this->admin->id, 'parent_id' => $grandparent->id]);
    $child = Folder::factory()->create(['created_by' => $this->admin->id, 'parent_id' => $parent->id]);

    $response = $this->actingAs($this->admin)->patchJson("/api/folders/{$grandparent->id}", [
        'parent_id' => $child->id,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['parent_id']);
});
