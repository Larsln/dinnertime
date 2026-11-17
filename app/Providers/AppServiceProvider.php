<?php

namespace App\Providers;

use App\Services\Mealie\MealieClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MealieClient::class, fn () => new MealieClient(
            (string) config('services.mealie.url'),
            (string) config('services.mealie.token'),
        ));
    }
}
