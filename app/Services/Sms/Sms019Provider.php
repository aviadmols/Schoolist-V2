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

    public function __construct()
    {
        $settings = SmsSetting::where('provider', 'sms019')->first();

        $this->username = $settings?->username ?? (string) config('services.sms019.username');
        $this->password = $settings?->password ?? (string) config('services.sms019.password');
        $this->sender = $settings?->sender ?? (string) config('services.sms019.sender');
    }

    /**
     * Send an SMS via 019 provider.
     */
    public function send(string $phone, string $message): bool
    {
        if (!$this->username || !$this->password || !$this->sender) {
            Log::error('SMS019 settings are incomplete. Cannot send SMS.');
            return false;
        }

        $payload = [
            'sms' => [
                'user' => [
                    'username' => $this->username,
                ],
                'source' => $this->sender,
                'destinations' => [
                    'phone' => [
                        [
                            '_' => ltrim($phone, '0'),
                        ],
                    ],
                ],
                'tag' => '#',
                'message' => $message,
                'add_dynamic' => '0',
                'add_unsubscribe' => '0',
                'includes_international' => '1',
            ],
        ];

        try {
            $authorization = base64_encode($this->username . ':' . $this->password);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $authorization,
                'Content-Type' => 'application/json',
            ])->post('https://019sms.co.il/api', $payload);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('SMS sending failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
