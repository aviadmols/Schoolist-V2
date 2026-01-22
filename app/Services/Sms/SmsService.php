<?php

namespace App\Services\Sms;

use App\Models\SmsLog;
use App\Models\SmsSetting;
use Illuminate\Support\Facades\Request;

class SmsService
{
    /** @var SmsProviderInterface */
    private SmsProviderInterface $provider;

    public function __construct(SmsProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Send an SMS message via the configured provider.
     */
    public function send(string $phone, string $message): bool
    {
        $log = SmsLog::create([
            'provider' => 'sms019',
            'phone_mask' => $this->maskPhone($phone),
            'status' => 'attempt',
            'request_id' => Request::header('X-Request-Id'),
            'user_id' => auth()->id(),
            'classroom_id' => Request::get('current_classroom')?->id,
            'error_message' => null,
        ]);

        $success = $this->provider->send($phone, $message);

        $log->update([
            'status' => $success ? 'sent' : 'failed',
            'error_message' => $success ? null : 'Provider returned failure',
        ]);

        return $success;
    }

    /**
     * Send an SMS and update an existing log.
     */
    public function sendWithLog(SmsLog $log, string $phone, string $message): bool
    {
        $log->update([
            'status' => 'attempt',
            'error_message' => null,
        ]);

        try {
            $success = $this->provider->send($phone, $message);
        } catch (\Exception $exception) {
            $log->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
            return false;
        }

        $log->update([
            'status' => $success ? 'sent' : 'failed',
            'error_message' => $success ? null : 'Provider returned failure',
        ]);

        return $success;
    }

    /**
     * Build the OTP message from settings.
     */
    public function buildOtpMessage(string $code): string
    {
        $setting = SmsSetting::where('provider', 'sms019')->first();
        $template = $setting?->otp_message_template ?: 'קוד האימות שלך הוא: {{code}}';

        return str_replace('{{code}}', $code, $template);
    }

    /**
     * Mask a phone number for logging.
     */
    private function maskPhone(string $phone): string
    {
        if (strlen($phone) < 7) {
            return $phone;
        }

        return substr($phone, 0, 3) . '****' . substr($phone, -3);
    }
}
