<?php

namespace Farayaz\LaravelOtp\Tests;

use Farayaz\LaravelOtp\Exceptions\LaravelOtpException;
use Farayaz\LaravelOtp\LaravelOtp;
use Farayaz\LaravelOtp\Models\Otp;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class LaravelOtpTest extends TestCase
{
    public function test_generated_code_has_exact_length_and_only_digits(): void
    {
        $otp = (new LaravelOtp)->generate('user', 10);

        $this->assertSame(10, strlen($otp->code));
        $this->assertMatchesRegularExpression('/^\d{10}$/D', $otp->code);
    }

    public function test_lock_timeout_throws_package_exception(): void
    {
        Cache::shouldReceive('lock')
            ->once()
            ->with('laravel-otp:generate:user', 10)
            ->andReturn(new class
            {
                public function block(): never
                {
                    throw new LockTimeoutException;
                }
            });

        $this->expectException(LaravelOtpException::class);
        $this->expectExceptionMessage('Unable to generate OTP. Please try again.');

        (new LaravelOtp)->generate('user');
    }

    public function test_invalid_lengths_are_rejected_without_overflow(): void
    {
        foreach ([0, 2, 11, PHP_INT_MAX, 1.5, '6', 'six'] as $length) {
            try {
                (new LaravelOtp)->generate('user', $length);
                $this->fail('An invalid length was accepted.');
            } catch (InvalidArgumentException) {
                $this->assertDatabaseCount('otps', 0);
            }
        }
    }

    public function test_configuration_defaults_and_explicit_arguments_are_supported(): void
    {
        config()->set('laravel-otp.length', 8);
        config()->set('laravel-otp.validity', 10);

        $configured = (new LaravelOtp)->generate('configured');
        $explicit = (new LaravelOtp)->generate('explicit', 4, 2);

        $this->assertSame(8, strlen($configured->code));
        $this->assertSame(4, strlen($explicit->code));
        $this->assertTrue($configured->expires_at->greaterThan($explicit->expires_at));
    }

    public function test_package_defaults_are_applied(): void
    {
        Carbon::setTestNow('2026-08-10 12:00:00');

        try {
            $service = new LaravelOtp;
            $otp = $service->generate('user');

            $this->assertSame(6, strlen($otp->code));
            $this->assertTrue($otp->expires_at->equalTo(now()->addMinutes(5)));

            foreach (range(1, 3) as $attempt) {
                $this->assertInvalid(fn () => $service->validate('user', 'wrong-'.$attempt));
            }

            $this->assertSame(3, $otp->fresh()->attempts);
            $this->assertInvalid(fn () => $service->validate('user', $otp->code));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_invalid_validity_is_rejected(): void
    {
        foreach ([0, -1, 1.5, true, '5', 'invalid', PHP_INT_MAX.'0'] as $validity) {
            try {
                (new LaravelOtp)->generate('user', 6, $validity);
                $this->fail('An invalid validity was accepted.');
            } catch (InvalidArgumentException) {
                $this->assertDatabaseCount('otps', 0);
            }
        }
    }

    public function test_new_generation_invalidates_previous_otp_and_resets_attempts(): void
    {
        $service = new LaravelOtp;
        $old = $service->generate('user');
        $old->update(['code' => 'old-code']);

        $this->assertInvalid(fn () => $service->isValid('user', 'not-'.$old->code));
        $this->assertSame(1, $old->fresh()->attempts);

        $new = $service->generate('user');

        $this->assertDatabaseCount('otps', 1);
        $this->assertSame($old->id, $new->id);
        $this->assertSame(0, $new->attempts);
        $this->assertInvalid(fn () => $service->isValid('user', $old->code));
    }

    public function test_expired_otp_is_rejected_with_generic_exception(): void
    {
        Carbon::setTestNow('2026-08-10 12:00:00');
        $otp = (new LaravelOtp)->generate('user', 6, 1);
        Carbon::setTestNow('2026-08-10 12:02:00');

        $this->assertInvalid(fn () => (new LaravelOtp)->validate('user', $otp->code));

        Carbon::setTestNow();
    }

    public function test_is_valid_does_not_consume_but_validate_does_and_replay_fails(): void
    {
        $service = new LaravelOtp;
        $otp = $service->generate('user');

        $this->assertSame($otp->id, $service->isValid('user', $otp->code)->id);
        $this->assertFalse($otp->fresh()->used);
        $this->assertTrue($service->validate('user', $otp->code)->used);
        $this->assertTrue($otp->fresh()->used);
        $this->assertInvalid(fn () => $service->validate('user', $otp->code));
    }

    public function test_wrong_codes_increment_attempts_and_lock_at_configured_maximum(): void
    {
        config()->set('laravel-otp.max_attempts', 2);
        $service = new LaravelOtp;
        $otp = $service->generate('user');

        $this->assertInvalid(fn () => $service->validate('user', 'wrong-one'));
        $this->assertSame(1, $otp->fresh()->attempts);
        $this->assertInvalid(fn () => $service->isValid('user', 'wrong-two'));
        $this->assertSame(2, $otp->fresh()->attempts);
        $this->assertInvalid(fn () => $service->validate('user', $otp->code));
        $this->assertFalse($otp->fresh()->used);
    }

    public function test_conditional_consumption_allows_only_one_success(): void
    {
        $service = new LaravelOtp;
        $otp = $service->generate('user');

        $this->assertTrue($service->validate('user', $otp->code)->used);
        $this->assertInvalid(fn () => (new LaravelOtp)->validate('user', $otp->code));
        $this->assertSame(1, Otp::query()->whereKey($otp->id)->where('used', true)->count());
    }

    private function assertInvalid(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected the OTP to be rejected.');
        } catch (LaravelOtpException $exception) {
            $this->assertSame('Invalid OTP', $exception->getMessage());
        }
    }
}
