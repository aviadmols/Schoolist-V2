<?php

namespace App\Services\Sms;

interface SmsProviderInterface
{
    /**
     * Send an SMS message.
     */
    public function send(string $phone, string $message): bool;
}
