<?php

namespace App\Providers;

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
        \Illuminate\Support\Facades\RateLimiter::for('scans', fn(\Illuminate\Http\Request $request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($request->ip()));
    }
}
