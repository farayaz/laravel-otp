<h1 align="center">Laravel OTP</h1>
<p align="center">
    <a href="https://github.com/farayaz/laravel-otp"><img src="https://img.shields.io/github/stars/farayaz/laravel-otp" alt="GitHub Repo stars"></a>
    <a href="https://packagist.org/packages/farayaz/laravel-otp"><img src="https://img.shields.io/packagist/dt/farayaz/laravel-otp" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/farayaz/laravel-otp"><img src="https://img.shields.io/packagist/v/farayaz/laravel-otp" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/farayaz/laravel-otp"><img src="https://img.shields.io/packagist/l/farayaz/laravel-otp" alt="License"></a>
</p>
simple laravel-otp package

## Usage

```php
$otp = OTP::generate('user@example.com');

OTP::isValid('user@example.com', $otp->code); // checks without consuming
OTP::validate('user@example.com', $otp->code); // checks and consumes atomically
```

All rejected OTPs (wrong, expired, already used, or attempt-locked) throw the same
`Farayaz\LaravelOtp\Exceptions\LaravelOtpException` with the message `Invalid OTP`.

Publish the optional configuration file with:

```shell
php artisan vendor:publish --tag=laravel-otp-config
```

It configures the default code length (`6`), validity in minutes (`5`), and maximum
failed attempts (`3`). Explicit `generate()` arguments take precedence over these
defaults. Supported OTP lengths are 3 through 10 digits, and validity must be a
positive integer number of minutes.

## Upgrading

Run `php artisan migrate` after upgrading. The additive package migration adds the
persistent `attempts` column and a unique identifier index. Test installations with
duplicate identifier rows must remove those duplicates before migrating. Generating
an OTP then atomically replaces the existing OTP state for that identifier.

## Compatibility

- Laravel 10
- Laravel 11
- Laravel 12
- Laravel 13
