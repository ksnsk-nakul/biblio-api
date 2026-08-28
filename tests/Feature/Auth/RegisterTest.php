<?php

use App\Models\User;

it('registers a new user and establishes a session', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.email', 'ada@example.com');
    $response->assertJsonPath('data.is_admin', false);

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
});

it('rejects registration with missing fields', function () {
    $response = $this->postJson('/api/register', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name', 'email', 'password']);
    $this->assertGuest();
});

it('rejects registration with a duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->postJson('/api/register', [
        'name' => 'Someone Else',
        'email' => 'taken@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
    $this->assertDatabaseCount('users', 1);
});
