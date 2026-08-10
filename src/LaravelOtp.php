<?php

namespace Farayaz\LaravelOtp;

use Farayaz\LaravelOtp\Exceptions\LaravelOtpException;
use Farayaz\LaravelOtp\Models\Otp as OtpModel;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class LaravelOtp
{
    public function generate($identifier, $length = null, $validity = null): OtpModel
    {
        $length ??= config('laravel-otp.length', 6);
        $validity ??= config('laravel-otp.validity', 5);

        if (! is_int($length) || $length < 3 || $length > 10) {
            throw new InvalidArgumentException('Length must be an integer between 3 and 10.');
        }

        if (! is_int($validity) || $validity < 1) {
            throw new InvalidArgumentException('Validity must be a positive integer.');
        }

        try {
            return Cache::lock('laravel-otp:generate:'.$identifier, 10)->block(
                5,
                fn () => OtpModel::updateOrCreate(
                    ['identifier' => $identifier],
                    [
                        'code' => $this->code($length),
                        'used' => false,
                        'attempts' => 0,
                        'expires_at' => now()->addMinutes($validity),
                    ],
                ),
            );
        } catch (LockTimeoutException $exception) {
            throw new LaravelOtpException('Unable to generate OTP. Please try again.', 0, $exception);
        }
    }

    public function isValid($identifier, $code): OtpModel
    {
        $otp = $this->validOtpQuery($identifier, $code)->first();

        if (! $otp) {
            $this->recordFailedAttempt($identifier, $code);

            throw new LaravelOtpException('Invalid OTP');
        }

        return $otp;
    }

    public function validate($identifier, $code): OtpModel
    {
        $otp = $this->validOtpQuery($identifier, $code)->first();

        if (! $otp) {
            $this->recordFailedAttempt($identifier, $code);

            throw new LaravelOtpException('Invalid OTP');
        }

        $consumed = OtpModel::query()
            ->where('identifier', $identifier)
            ->where('code', $code)
            ->where('used', false)
            ->where('attempts', '<', $this->maximumAttempts())
            ->where('expires_at', '>', now())
            ->update(['used' => true]);

        if ($consumed < 1) {
            $this->recordFailedAttempt($identifier, $code);

            throw new LaravelOtpException('Invalid OTP');
        }

        $otp->used = true;

        return $otp;
    }

    private function validOtpQuery($identifier, $code): Builder
    {
        return OtpModel::query()
            ->where('identifier', $identifier)
            ->where('code', $code)
            ->where('used', false)
            ->where('attempts', '<', $this->maximumAttempts())
            ->where('expires_at', '>', now());
    }

    private function recordFailedAttempt($identifier, $code): void
    {
        OtpModel::query()
            ->where('identifier', $identifier)
            ->where('code', '!=', $code)
            ->where('used', false)
            ->where('attempts', '<', $this->maximumAttempts())
            ->where('expires_at', '>', now())
            ->increment('attempts');
    }

    private function maximumAttempts(): int
    {
        $maximum = config('laravel-otp.max_attempts', 3);

        if (! is_int($maximum) && ! (is_string($maximum) && ctype_digit($maximum))) {
            throw new InvalidArgumentException('Maximum attempts must be a positive integer.');
        }

        $maximum = (int) $maximum;

        if ($maximum < 1) {
            throw new InvalidArgumentException('Maximum attempts must be a positive integer.');
        }

        return $maximum;
    }

    private function code(int $length): string
    {
        $code = '';

        for ($position = 0; $position < $length; $position++) {
            $code .= (string) random_int(0, 9);
        }

        return $code;
    }
}
