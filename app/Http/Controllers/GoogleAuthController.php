<?php

namespace App\Http\Controllers;

use App\Exceptions\GoogleLoginException;
use App\Services\GoogleLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/** "Continue with Google": off to Google's consent screen, then back here to sign in. */
class GoogleAuthController extends Controller
{
    public function __construct(private readonly GoogleLoginService $google) {}

    public function redirect(): SymfonyRedirect
    {
        abort_unless(config('services.google.client_id'), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(config('services.google.client_id'), 404);

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            // Cancelled on Google's screen, an expired state, or Google unreachable.
            return $this->backToLogin('Google sign-in was cancelled or did not finish. Please try again.');
        }

        try {
            $user = $this->google->userFor($googleUser);
        } catch (GoogleLoginException $exception) {
            return $this->backToLogin($exception->getMessage());
        }

        // Two-factor still applies: hand over to Fortify's code challenge, as a password login would.
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => true]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function backToLogin(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['google' => $message]);
    }
}
