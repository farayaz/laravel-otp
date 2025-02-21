<?php

namespace Farayaz\LaravelOtp;

use Illuminate\Support\Facades\Facade;

class LaravelOtpFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    public static function getFacadeAccessor()
    {
        return 'laravel-otp';
    }
}
