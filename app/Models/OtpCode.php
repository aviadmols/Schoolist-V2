<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'phone',
        'code_hash',
        'attempts',
        'expires_at',
        'verified_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /**
     * Check if the OTP is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the OTP is already verified.
     */
    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }
}
