<?php

namespace Farayaz\LaravelOtp;

use Illuminate\Support\ServiceProvider;

class LaravelOtpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-otp.php', 'laravel-otp');

        $this->app->singleton('laravel-otp', fn () => new LaravelOtp);
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/laravel-otp.php' => config_path('laravel-otp.php'),
        ], 'laravel-otp-config');
    }
}
