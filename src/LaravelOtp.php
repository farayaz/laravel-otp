<?php

namespace Farayaz\LaravelOtp;

use Farayaz\LaravelOtp\Exceptions\LaravelOtpException;
use Farayaz\LaravelOtp\Models\Otp as OtpModel;
use InvalidArgumentException;

class LaravelOtp
{
    public function generate($identifier, $length = 6, $validity = 5)
    {
        OtpModel::where('identifier', $identifier)->delete();

        $otp = OtpModel::create([
            'identifier' => $identifier,
            'code' => $this->code($length),
            'expires_at' => now()->addMinutes($validity),
        ]);

        return $otp;
    }

    public function isValid($identifier, $code)
    {
        $otp = OtpModel::query()
            ->where('identifier', $identifier)
            ->where('code', $code)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (! $otp) {
            throw new LaravelOtpException('Invalid OTP');
        }

        return $otp;
    }

    public function validate($identifier, $code)
    {
        try {
            $otp = $this->isValid($identifier, $code);
            $otp->update([
                'used' => true,
            ]);
        } catch (LaravelOtpException $e) {
            throw $e;
        }

        return $otp;
    }

    private function code($length)
    {
        if ($length <= 0) {
            throw new InvalidArgumentException('Length must be greater than 0.');
        }

        $max = pow(10, $length) - 1;
        $randomNumber = mt_rand(0, $max);

        return str_pad($randomNumber, $length, '0', STR_PAD_LEFT);
    }
}
