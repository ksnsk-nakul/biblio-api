<?php

use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

function mockGoogleUser(string $id, string $email, string $name = 'Google User'): void
{
    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn($id);
    $socialiteUser->shouldReceive('getEmail')->andReturn($email);
    $socialiteUser->shouldReceive('getName')->andReturn($name);
    $socialiteUser->shouldReceive('getNickname')->andReturn(null);

    $provider = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

it('creates a new user and logs them in for a brand new google email', function () {
    mockGoogleUser('google-123', 'new-user@example.com');

    $response = $this->get('/api/auth/google/callback');

    $response->assertRedirect(config('bibliocon.frontend_url'));
    $this->assertAuthenticated();

    $user = User::where('email', 'new-user@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->google_id)->toBe('google-123');
});

it('logs in an existing user who already has this google_id linked', function () {
    $user = User::factory()->create([
        'email' => 'linked@example.com',
        'google_id' => 'google-456',
    ]);

    mockGoogleUser('google-456', 'linked@example.com');

    $response = $this->get('/api/auth/google/callback');

    $response->assertRedirect(config('bibliocon.frontend_url'));
    $this->assertAuthenticatedAs($user);
});

it('does not silently link an existing password account and redirects with an error', function () {
    $user = User::factory()->create([
        'email' => 'victim@example.com',
        'google_id' => null,
    ]);

    mockGoogleUser('google-789', 'victim@example.com');

    $response = $this->get('/api/auth/google/callback');

    $response->assertRedirect(config('bibliocon.frontend_url').'?auth_error=email_already_registered');
    $this->assertGuest();

    expect($user->fresh()->google_id)->toBeNull();
});
