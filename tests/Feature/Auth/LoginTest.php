<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('login');
});

it('logs in with valid credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-password')]);

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.email', $user->email);
    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-password')]);

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
    $this->assertGuest();
});

it('throttles repeated rapid login attempts', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-password')]);

    $payload = ['email' => $user->email, 'password' => 'wrong-password'];

    for ($i = 0; $i < 6; $i++) {
        $response = $this->postJson('/api/login', $payload);
        $response->assertStatus(422);
    }

    $response = $this->postJson('/api/login', $payload);
    $response->assertStatus(429);
});
