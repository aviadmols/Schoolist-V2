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
        // 019 API implementation details would go here.
        // For now, we simulate the XML/JSON request.
        
        try {
            // Note: Never log the message if it contains OTP in a real log.
            // But here we just return success for the structure.
            
            // Example 019 XML request (pseudo-code)
            /*
            $response = Http::post('https://019sms.co.il/api/send_sms.php', [
                'username' => $this->username,
                'password' => $this->password,
                'sender' => $this->sender,
                'message' => $message,
                'phone' => $phone,
            ]);
            return $response->successful();
            */
            
            return true;
        } catch (\Exception $e) {
            Log::error('SMS sending failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
