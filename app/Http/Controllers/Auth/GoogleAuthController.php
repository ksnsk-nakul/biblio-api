<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\FindOrCreateGoogleUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    // Standard Socialite + SPA pattern: the callback runs on the API domain,
    // establishes the stateful Sanctum session, then redirects the browser
    // back to the SPA (which then calls GET /api/user to hydrate auth state).
    public function callback(Request $request, FindOrCreateGoogleUser $findOrCreateGoogleUser): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

        try {
            $user = $findOrCreateGoogleUser->execute($googleUser);
        } catch (ValidationException) {
            return redirect()->away(
                config('bibliocon.frontend_url').'?auth_error=email_already_registered'
            );
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->away(config('bibliocon.frontend_url'));
    }
}
