<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Models\OtpCode;
use App\Services\Classroom\ClassroomService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RegisterController
{
    /**
     * Register a new user after OTP verification.
     */
    public function __invoke(Request $request, ClassroomService $classroomService)
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'unique:users,email'],
            'join_code' => ['nullable', 'string', 'size:4'],
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

        // Join classroom if join code provided
        $classroom = null;
        if ($request->join_code) {
            $classroom = $classroomService->joinWithCode($user, $request->join_code);
            if (!$classroom) {
                return response()->json(['message' => 'קוד הכיתה שגוי.'], 422);
            }
        }

        Auth::login($user);

        Log::info('New user registered', [
            'user_id' => $user->id,
            'classroom_id' => $classroom?->id,
            'request_id' => $request->header('X-Request-Id'),
        ]);

        $redirectUrl = $classroom 
            ? route('classroom.show', $classroom) 
            : route('profile.show');

        return response()->json(['redirect' => $redirectUrl]);
    }
}
