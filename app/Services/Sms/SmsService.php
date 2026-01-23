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
        $setting = SmsSetting::where('provider', 'sms019')->first();
        $providerRequest = [
            'sender' => $setting?->sender ?? (string) config('services.sms019.sender'),
            'phone_mask' => $this->maskPhone($phone),
            'message_length' => strlen($message),
        ];
        $providerRequestJson = json_encode($providerRequest, JSON_UNESCAPED_SLASHES) ?: '';

        $log = SmsLog::create([
            'provider' => 'sms019',
            'phone_mask' => $this->maskPhone($phone),
            'status' => 'attempt',
            'request_id' => Request::header('X-Request-Id'),
            'user_id' => auth()->id(),
            'classroom_id' => Request::get('current_classroom')?->id,
            'error_message' => null,
            'provider_request' => $providerRequestJson,
        ]);

        $result = $this->provider->send($phone, $message);
        $success = $result->isSuccess();

        $log->update([
            'status' => $success ? 'sent' : 'failed',
            'error_message' => $success ? null : ($result->getErrorMessage() ?: 'Provider returned failure'),
            'provider_response' => $this->formatProviderResponse($result),
        ]);

        return $success;
    }

    /**
     * Send an SMS and update an existing log.
     */
    public function sendWithLog(SmsLog $log, string $phone, string $message): bool
    {
        $setting = SmsSetting::where('provider', 'sms019')->first();
        $providerRequest = [
            'sender' => $setting?->sender ?? (string) config('services.sms019.sender'),
            'phone_mask' => $this->maskPhone($phone),
            'message_length' => strlen($message),
        ];
        $providerRequestJson = json_encode($providerRequest, JSON_UNESCAPED_SLASHES) ?: '';

        $log->update([
            'status' => 'attempt',
            'error_message' => null,
            'provider_request' => $providerRequestJson,
        ]);

        $result = $this->provider->send($phone, $message);
        $success = $result->isSuccess();

        $log->update([
            'status' => $success ? 'sent' : 'failed',
            'error_message' => $success ? null : ($result->getErrorMessage() ?: 'Provider returned failure'),
            'provider_response' => $this->formatProviderResponse($result),
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

    /**
     * Format provider response for logging.
     */
    private function formatProviderResponse(SmsSendResult $result): string
    {
        $payload = [
            'success' => $result->isSuccess(),
            'status_code' => $result->getStatusCode(),
            'error' => $result->getErrorMessage(),
            'body' => $this->truncateResponseBody($result->getResponseBody()),
        ];

        return json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '';
    }

    /**
     * Truncate response body for storage.
     */
    private function truncateResponseBody(?string $body): ?string
    {
        if (!$body) {
            return null;
        }

        $maxLength = 2000;

        if (strlen($body) <= $maxLength) {
            return $body;
        }

        return substr($body, 0, $maxLength).'...';
    }
}
