<?php

namespace Farayaz\LaravelOtp;

use Illuminate\Support\ServiceProvider;

class LaravelOtpServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('laravel-otp', function ($app) {
            return new LaravelOtp;
        });
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
