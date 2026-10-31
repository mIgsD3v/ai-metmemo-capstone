<?php

namespace App\Services;

use App\Exceptions\GoogleLoginException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as GoogleUser;

/**
 * Finds or creates the MetMemo account for someone who chose "Continue with Google".
 *
 * Order: an account already linked to this Google id; else an account with the same email, linked now (only when
 * Google says it verified that email, so nobody can take over an account with an address they don't own); else a
 * new account.
 */
class GoogleLoginService
{
    public function userFor(GoogleUser $google): User
    {
        $linked = User::query()->where('google_id', $google->getId())->first();

        if ($linked) {
            return $linked;
        }

        $email = Str::lower(trim((string) $google->getEmail()));

        if ($email === '' || ! ($google->getRaw()['email_verified'] ?? false)) {
            throw new GoogleLoginException("Google didn't confirm an email address for this account, so we can't sign you in with it.");
        }

        $existing = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($existing) {
            $existing->forceFill([
                'google_id' => $google->getId(),
                'email_verified_at' => $existing->email_verified_at ?? now(),
            ])->save();

            return $existing;
        }

        // A new account. Its password is random and never shown: the owner signs in with Google, or sets a
        // password later with "Forgot password".
        return User::forceCreate([
            'name' => $google->getName() ?: Str::before($email, '@'),
            'email' => $email,
            'google_id' => $google->getId(),
            'email_verified_at' => now(),
            'password' => Hash::make(Str::random(64)),
        ]);
    }
}
