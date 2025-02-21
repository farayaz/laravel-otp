<?php

namespace Farayaz\LaravelOtp;

use Farayaz\LaravelOtp\Exceptions\LaravelOtpException;
use Farayaz\LaravelOtp\Models\Otp as OtpModel;

class LaravelOtp
{
    public function generate($identifier, $length = 6, $validity = 5)
    {
        $otp = OtpModel::create([
            'identifier' => $identifier,
            'code' => random_int(900000, 999999),
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
}
