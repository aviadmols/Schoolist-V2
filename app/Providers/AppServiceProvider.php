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
        $this->app->bind(
            \App\Services\Sms\SmsProviderInterface::class,
            \App\Services\Sms\Sms019Provider::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ensure framework directories exist for Railway/Production
        $paths = [
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('app/livewire-tmp'),
        ];

        foreach ($paths as $path) {
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }
        }

        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        \Illuminate\Support\Facades\RateLimiter::for('otp', function (\Illuminate\Http\Request $request) {
            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(3)->by($request->ip()),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(3)->by($request->phone),
            ];
        });
    }
}

