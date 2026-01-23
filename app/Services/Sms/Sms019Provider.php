<?php

namespace App\Services\Sms;

use App\Models\SmsSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Sms019Provider implements SmsProviderInterface
{
    /** @var string */
    private string $username;

    /** @var string */
    private string $password;

    /** @var string */
    private string $sender;

    /** @var string */
    private string $token;

    public function __construct()
    {
        $settings = SmsSetting::where('provider', 'sms019')->first();

        $this->username = $settings?->username ?? (string) config('services.sms019.username');
        $this->sender = $settings?->sender ?? (string) config('services.sms019.sender');
        $this->token = $settings?->token ?? (string) config('services.sms019.token');
    }

    /**
     * Send an SMS via 019 provider.
     */
    public function send(string $phone, string $message): SmsSendResult
    {
        if (!$this->username || !$this->sender || !$this->token) {
            Log::error('SMS019 settings are incomplete. Cannot send SMS.');
            return new SmsSendResult(false, null, null, 'Missing SMS019 settings.');
        }

        $payload = [
            'sms' => [
                'user' => [
                    'username' => $this->username,
                ],
                'source' => $this->sender,
                'destinations' => [
                    'phone' => [
                        ltrim($phone, '0'),
                    ],
                ],
                'tag' => '#',
                'message' => $message,
                'add_dynamic' => '0',
                'add_unsubscribe' => '0',
                'includes_international' => '0',
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->token,
            ])
                ->asJson()
                ->post('https://019sms.co.il/api', $payload);

            $errorMessage = $response->successful() ? null : 'Provider returned failure';

            return new SmsSendResult(
                $response->successful(),
                $response->status(),
                $response->body(),
                $errorMessage
            );
        } catch (\Exception $e) {
            Log::error('SMS sending failed', ['error' => $e->getMessage()]);
            return new SmsSendResult(false, null, null, $e->getMessage());
        }
    }
}
