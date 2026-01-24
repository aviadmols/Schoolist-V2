<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class VerifyOtpController
{
    /**
     * Verify the OTP code and authenticate the user.
     */
    public function __invoke(Request $request, OtpService $otpService)
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'code' => ['required', 'string', 'size:4'],
        ]);

        $phone = $request->phone;
        $code = $request->code;

        if (!$otpService->verify($phone, $code)) {
            Log::warning('OTP verification failed', [
                'phone_mask' => substr($phone, 0, 3) . '****' . substr($phone, -3),
                'request_id' => $request->header('X-Request-Id'),
            ]);

            throw ValidationException::withMessages([
                'code' => ['הקוד שגוי או שפג תוקפו.'],
            ]);
        }

        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return response()->json([
                'requires_registration' => true,
                'phone' => $phone,
                'message' => 'לא נמצא משתמש עם המספר הזה. יש להשלים רישום.',
            ]);
        }

        Auth::login($user);

        Log::info('User authenticated via OTP', [
            'user_id' => $user->id,
            'request_id' => $request->header('X-Request-Id'),
        ]);

        return response()->json([
            'redirect' => route('landing'),
        ]);
    }
}
