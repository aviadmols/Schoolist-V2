<?php

namespace App\Services\Sms;

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
        return $this->provider->send($phone, $message);
    }
}
