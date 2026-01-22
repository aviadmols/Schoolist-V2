<?php

namespace App\Services\Sms;

class SmsSendResult
{
    /** @var bool */
    private bool $success;

    /** @var int|null */
    private ?int $statusCode;

    /** @var string|null */
    private ?string $responseBody;

    /** @var string|null */
    private ?string $errorMessage;

    /**
     * Create a new result instance.
     */
    public function __construct(bool $success, ?int $statusCode, ?string $responseBody, ?string $errorMessage)
    {
        $this->success = $success;
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
        $this->errorMessage = $errorMessage;
    }

    /**
     * Determine if the send succeeded.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * Get the raw response body.
     */
    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    /**
     * Get the error message.
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
