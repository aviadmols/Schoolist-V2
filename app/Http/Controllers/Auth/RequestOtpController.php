<?php

namespace App\Http\Controllers\Auth;

use App\Jobs\SendOtpSmsJob;
use App\Models\SmsLog;
use App\Models\SmsSetting;
use App\Services\Auth\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RequestOtpController
{
    /**
     * Request a new OTP code.
     */
    public function __invoke(Request $request, OtpService $otpService)
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
        ]);

        $phone = $request->phone;

        // Structured JSON log (concept)
        Log::info('OTP requested', [
            'phone_mask' => substr($phone, 0, 3) . '****' . substr($phone, -3),
            'ip' => $request->ip(),
            'request_id' => $request->header('X-Request-Id'),
        ]);

        $code = $otpService->generate($phone);
        $setting = SmsSetting::where('provider', 'sms019')->first();
        $providerRequest = [
            'sender' => $setting?->sender ?? (string) config('services.sms019.sender'),
            'phone_mask' => substr($phone, 0, 3) . '****' . substr($phone, -3),
            'message_length' => 0,
        ];
        $providerRequestJson = json_encode($providerRequest, JSON_UNESCAPED_SLASHES) ?: '';

        $log = SmsLog::create([
            'provider' => 'sms019',
            'phone_mask' => substr($phone, 0, 3) . '****' . substr($phone, -3),
            'status' => 'queued',
            'request_id' => $request->header('X-Request-Id'),
            'user_id' => auth()->id(),
            'classroom_id' => $request->attributes->get('current_classroom')?->id,
            'error_message' => null,
            'provider_request' => $providerRequestJson,
        ]);

        // Dispatch job to send SMS
        SendOtpSmsJob::dispatch($phone, $code, $log->id);

        return response()->json([
            'message' => 'הקוד נשלח בהצלחה.',
        ]);
    }
}
