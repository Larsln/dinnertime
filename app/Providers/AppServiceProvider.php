<?php

namespace App\Providers;

use App\Services\Ai\GeminiClient;
use App\Services\Auth\OidcVerifier;
use App\Services\Mealie\MealieClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GeminiClient::class, fn () => new GeminiClient(
            config('services.gemini.key'),
            (string) config('services.gemini.base_url'),
        ));

        $this->app->singleton(OidcVerifier::class, fn () => new OidcVerifier(
            config('services.oidc.issuer'),
            config('services.oidc.client_id'),
        ));

        $this->app->singleton(MealieClient::class, fn () => new MealieClient(
            (string) config('services.mealie.url'),
            (string) config('services.mealie.token'),
        ));
    }
}
