<?php

namespace Farayaz\LaravelOtp\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'identifier',
        'code',
        'used',
        'expires_at',
    ];
}
