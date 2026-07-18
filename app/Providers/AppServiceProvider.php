<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // In production, generate every URL as HTTPS (works behind a TLS-terminating proxy).
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
