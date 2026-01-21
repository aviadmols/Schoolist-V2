<?php

namespace App\Services\Auth;

use App\Models\OtpCode;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    /** @var int */
    private const CODE_LENGTH = 6;

    /** @var int */
    private const EXPIRES_IN_MINUTES = 5;

    /** @var int */
    private const MAX_ATTEMPTS = 3;

    /**
     * Generate a new OTP for the given phone.
     */
    public function generate(string $phone): string
    {
        $code = (string) rand(pow(10, self::CODE_LENGTH - 1), pow(10, self::CODE_LENGTH) - 1);

        OtpCode::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => Carbon::now()->addMinutes(self::EXPIRES_IN_MINUTES),
            'attempts' => 0,
        ]);

        return $code;
    }

    /**
     * Generate a one-time OTP without sending SMS.
     */
    public function generateManual(string $phone): string
    {
        return $this->generate($phone);
    }

    /**
     * Verify the OTP for the given phone.
     */
    public function verify(string $phone, string $code): bool
    {
        $otp = OtpCode::where('phone', $phone)
            ->whereNull('verified_at')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$otp || $otp->isExpired() || $otp->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (!Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            return false;
        }

        $otp->update(['verified_at' => Carbon::now()]);

        return true;
    }
}
