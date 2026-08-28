<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class FindOrCreateGoogleUser
{
    /**
     * @throws ValidationException if an existing account with this email
     *         is not already linked to this Google identity. Silently
     *         linking here would let an attacker who pre-registers a
     *         victim's email with a password take over the account once
     *         the victim signs in with Google.
     */
    public function execute(SocialiteUser $googleUser): User
    {
        $user = User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            if ($user->google_id && $user->google_id === $googleUser->getId()) {
                return $user;
            }

            throw ValidationException::withMessages([
                'email' => 'An account with this email already exists.',
            ]);
        }

        return User::create([
            'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: $googleUser->getEmail(),
            'email' => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
            'password' => Hash::make(Str::random(40)),
            'email_verified_at' => now(),
        ]);
    }
}
