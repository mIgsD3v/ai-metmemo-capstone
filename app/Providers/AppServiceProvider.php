<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Ai\AiServiceClient;
use App\Support\Access;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiServiceClient::class, fn () => new AiServiceClient(
            baseUrl: config('services.ai.url'),
            token: config('services.ai.token'),
            timeout: config('services.ai.timeout'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // A Super Admin has every permission. Only permissions: ownership checks (someone's contact, card,
        // chat) still apply, so no role can open another account's data.
        Gate::before(fn (User $user, string $ability) => in_array($ability, Access::all(), true) && $user->hasRole(Access::SUPER_ADMIN) ? true : null);

        // Visitors write the names shown in emails: "[Click](https://...)" on the lead form must stay plain text.
        Markdown::withSecuredEncoding();
    }
}
