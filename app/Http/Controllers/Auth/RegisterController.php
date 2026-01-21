<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Models\OtpCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RegisterController
{
    /**
     * Register a new user after OTP verification.
     */
    public function __invoke(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'unique:users,email'],
        ]);

        // Ensure the phone was actually verified recently
        $verified = OtpCode::where('phone', $request->phone)
            ->whereNotNull('verified_at')
            ->where('verified_at', '>', now()->subMinutes(15))
            ->exists();

        if (!$verified) {
            return response()->json(['message' => 'הטלפון לא אומת.'], 403);
        }

        $name = trim($request->first_name . ' ' . $request->last_name);

        $user = User::create([
            'phone' => $request->phone,
            'name' => $name,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'role' => 'user',
        ]);

        Auth::login($user);

        Log::info('New user registered', [
            'user_id' => $user->id,
            'request_id' => $request->header('X-Request-Id'),
        ]);

        return response()->json(['redirect' => route('landing')]);
    }
}
