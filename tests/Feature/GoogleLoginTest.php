<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use RuntimeException;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => 'test-client', 'services.google.client_secret' => 'test-secret']);
    }

    /** Google answers the callback with this account. */
    private function googleReturns(string $id, string $email, bool $verified = true, string $name = 'Ana Reyes'): void
    {
        $user = (new GoogleUser)
            ->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => $verified])
            ->map(['id' => $id, 'name' => $name, 'email' => $email]);

        Socialite::shouldReceive('driver->user')->andReturn($user);
    }

    public function test_first_google_sign_in_creates_a_verified_account(): void
    {
        $this->googleReturns('google-1', 'Ana@Example.com');

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $user = User::firstWhere('email', 'ana@example.com');
        $this->assertNotNull($user);
        $this->assertSame('Ana Reyes', $user->name);
        $this->assertSame('google-1', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_returning_google_user_signs_in_without_a_second_account(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $user->forceFill(['google_id' => 'google-1'])->save();
        // Even if the Google account's email changed since, the id is what links it.
        $this->googleReturns('google-1', 'ana.new@example.com');

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
    }

    public function test_existing_password_account_is_linked_when_google_verified_the_email(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $this->googleReturns('google-1', 'ANA@example.com');

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-1', $user->fresh()->google_id);
        $this->assertSame(1, User::count());
    }

    public function test_unverified_google_email_is_refused(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $this->googleReturns('google-1', 'ana@example.com', verified: false);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_cancelled_google_sign_in_goes_back_to_login_with_a_message(): void
    {
        Socialite::shouldReceive('driver->user')->andThrow(new RuntimeException('access_denied'));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
    }

    public function test_two_factor_still_applies_to_google_sign_in(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $user->forceFill([
            'google_id' => 'google-1',
            'two_factor_secret' => encrypt('secret'),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $this->googleReturns('google-1', 'ana@example.com');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('two-factor.login'))
            ->assertSessionHas('login.id', $user->id);

        $this->assertGuest();
    }

    public function test_google_routes_are_off_until_a_client_is_configured(): void
    {
        config(['services.google.client_id' => null]);

        $this->get(route('auth.google'))->assertNotFound();
        $this->get(route('auth.google.callback'))->assertNotFound();
    }
}
