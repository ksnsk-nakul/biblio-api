<?php

use App\Models\User;

it('returns 401 for /api/user when unauthenticated', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

it('returns the authenticated user payload including is_admin', function () {
    config(['bibliocon.admin_email' => 'admin@bibliocon.test']);
    $admin = User::factory()->create(['email' => 'admin@bibliocon.test']);

    $response = $this->actingAs($admin)->getJson('/api/user');

    $response->assertOk();
    $response->assertJson([
        'data' => [
            'id' => $admin->id,
            'email' => 'admin@bibliocon.test',
            'is_admin' => true,
        ],
    ]);
});

it('returns is_admin false for a regular user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/user');

    $response->assertOk();
    $response->assertJsonPath('data.is_admin', false);
});

it('invalidates the session on logout', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-password')]);

    // Log in for real (rather than actingAs, which forces auth on the guard
    // independent of the session cookie) so we can verify the session is
    // genuinely invalidated by logout.
    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ])->assertOk();

    $this->getJson('/api/user')->assertOk();
    // Sanctum's auth:sanctum middleware calls Auth::shouldUse('sanctum') on
    // success, which flips the *default* guard for the rest of the process.
    // The actual session-backed guard used by login/logout is 'web', so
    // check that one explicitly rather than the (now-mutated) default.
    $this->assertAuthenticatedAs($user, 'web');

    $this->postJson('/api/logout')->assertNoContent();

    $this->assertGuest('web');
});
