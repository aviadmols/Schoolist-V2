<?php

namespace App\Services\Ai;

use App\Services\Audit\AuditService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OpenRouterService
{
    /** @var string */
    private const BASE_URL = 'https://openrouter.ai/api/v1';

    /** @var string */
    private const AUDIT_EVENT = 'openrouter_request';

    /** @var int */
    private const REQUEST_TIMEOUT_SECONDS = 120;

    /** @var int */
    private const ERROR_BODY_LIMIT = 500;

    /** @var int */
    private const PROMPT_PREVIEW_LIMIT = 2000;

    /** @var int */
    private const RESPONSE_PREVIEW_LIMIT = 2000;

    /** @var string|null */
    private ?string $lastError = null;

    /**
     * Get the last error message.
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Test the OpenRouter connection.
     */
    public function testConnection(string $token): bool
    {
        $this->clearLastError();

        $response = $this->sendRequest($token, 'GET', self::BASE_URL.'/models');

        return $response->ok();
    }

    /**
     * Check if a model is available for the token.
     */
    public function isModelAvailable(string $token, string $model): bool
    {
        $this->clearLastError();

        try {
            $response = $this->sendRequest($token, 'GET', self::BASE_URL.'/models');
        } catch (\Throwable $exception) {
            $this->lastError = $exception->getMessage();
            return false;
        }

        if (!$response->ok()) {
            $this->lastError = $this->buildErrorMessage($response);
            return false;
        }

        $data = $response->json('data');
        if (!is_array($data)) {
            $this->lastError = 'OpenRouter models response invalid.';
            return false;
        }

        foreach ($data as $item) {
            if (($item['id'] ?? null) === $model) {
                return true;
            }
        }

        $this->lastError = 'Model not found: '.$model;
        return false;
    }

    /**
     * Request a timetable extraction completion.
     *
     * @return string|null
     */
    public function requestTimetableExtraction(
        string $token,
        string $model,
        string $prompt,
        string $imageMime,
        string $imageBase64,
        ?int $classroomId = null
    ): ?string {
        $this->clearLastError();
        $requestPayload = $this->buildRequestLogPayload('timetable_extraction', $model, $prompt, $imageMime, $imageBase64);

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$imageMime.';base64,'.$imageBase64]],
                    ],
                ],
            ],
        ];

        try {
            $response = $this->sendRequest($token, 'POST', self::BASE_URL.'/chat/completions', $payload);
        } catch (\Throwable $exception) {
            $this->lastError = $exception->getMessage();
            $this->recordAuditLog($requestPayload, $this->buildResponseLogPayload(null, null, $this->lastError), $classroomId);
            return null;
        }

        $content = $this->resolveResponseContent($response);
        $this->recordAuditLog($requestPayload, $this->buildResponseLogPayload($response, $content, $this->lastError), $classroomId);

        return $content;
    }

    /**
     * Request a template HTML update.
     *
     * @return string|null
     */
    public function requestTemplateUpdate(string $token, string $model, string $prompt, ?int $classroomId = null): ?string
    {
        $this->clearLastError();
        $requestPayload = $this->buildRequestLogPayload('template_update', $model, $prompt, null, null);

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ];

        try {
            $response = $this->sendRequest($token, 'POST', self::BASE_URL.'/chat/completions', $payload);
        } catch (\Throwable $exception) {
            $this->lastError = $exception->getMessage();
            $this->recordAuditLog($requestPayload, $this->buildResponseLogPayload(null, null, $this->lastError), $classroomId);
            return null;
        }

        $content = $this->resolveResponseContent($response);
        $this->recordAuditLog($requestPayload, $this->buildResponseLogPayload($response, $content, $this->lastError), $classroomId);

        return $content;
    }

    /**
     * Request content analysis for text or image.
     *
     * @return string|null
     */
    public function requestContentAnalysis(
        string $token,
        string $model,
        string $prompt,
        ?string $imageMime = null,
        ?string $imageBase64 = null,
        ?int $classroomId = null
    ): ?string {
        $this->clearLastError();
        $requestPayload = $this->buildRequestLogPayload('content_analysis', $model, $prompt, $imageMime, $imageBase64);

        $content = [
            ['type' => 'text', 'text' => $prompt],
        ];

        if ($imageMime && $imageBase64) {
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$imageMime.';base64,'.$imageBase64]];
        }

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
        ];

        try {
            $response = $this->sendRequest($token, 'POST', self::BASE_URL.'/chat/completions', $payload);
        } catch (\Throwable $exception) {
            $this->lastError = $exception->getMessage();
            $this->recordAuditLog($requestPayload, $this->buildResponseLogPayload(null, null, $this->lastError), $classroomId);
            return null;
        }

        $content = $this->resolveResponseContent($response);
        $this->recordAuditLog($requestPayload, $this->buildResponseLogPayload($response, $content, $this->lastError), $classroomId);

        return $content;
    }

    /**
     * Send a request to OpenRouter.
     */
    private function sendRequest(string $token, string $method, string $url, array $payload = []): Response
    {
        $client = Http::withToken($token)
            ->acceptJson()
            ->timeout(self::REQUEST_TIMEOUT_SECONDS);

        if ($method === 'GET') {
            return $client->get($url);
        }

        return $client->post($url, $payload);
    }

    /**
     * Build a request payload for audit logging.
     *
     * @return array<string, mixed>
     */
    private function buildRequestLogPayload(
        string $type,
        string $model,
        string $prompt,
        ?string $imageMime,
        ?string $imageBase64
    ): array {
        $promptText = trim($prompt);

        return [
            'type' => $type,
            'model' => $model,
            'prompt_length' => strlen($promptText),
            'prompt_preview' => $this->truncateText($promptText, self::PROMPT_PREVIEW_LIMIT),
            'has_image' => $imageMime !== null && $imageBase64 !== null,
            'image_mime' => $imageMime,
            'image_size' => $imageBase64 ? strlen($imageBase64) : null,
        ];
    }

    /**
     * Build a response payload for audit logging.
     *
     * @return array<string, mixed>
     */
    private function buildResponseLogPayload(?Response $response, ?string $content, ?string $error): array
    {
        $contentText = $content ? trim($content) : '';

        return [
            'status' => $response ? $response->status() : null,
            'content_length' => $contentText !== '' ? strlen($contentText) : null,
            'content_preview' => $contentText !== '' ? $this->truncateText($contentText, self::RESPONSE_PREVIEW_LIMIT) : null,
            'error' => $error,
        ];
    }

    /**
     * Record the request/response in the audit log.
     */
    private function recordAuditLog(array $requestPayload, array $responsePayload, ?int $classroomId): void
    {
        app(AuditService::class)->log(
            self::AUDIT_EVENT,
            null,
            null,
            [
                'request' => $requestPayload,
                'response' => $responsePayload,
            ],
            $classroomId
        );
    }

    /**
     * Resolve response content or store an error message.
     */
    private function resolveResponseContent(Response $response): ?string
    {
        if (!$response->ok()) {
            $this->lastError = $this->buildErrorMessage($response);
            return null;
        }

        $content = $response->json('choices.0.message.content');
        if (!is_string($content) || trim($content) === '') {
            $this->lastError = 'OpenRouter response missing content.';
            return null;
        }

        return $content;
    }

    /**
     * Build a readable error message from the response.
     */
    private function buildErrorMessage(Response $response): string
    {
        $status = $response->status();
        $message = (string) $response->json('error.message', '');
        if ($message !== '') {
            return 'HTTP '.$status.': '.$message;
        }

        $body = trim($response->body());
        if ($body === '') {
            return 'HTTP '.$status.': Empty response body.';
        }

        $truncated = mb_substr($body, 0, self::ERROR_BODY_LIMIT);

        return 'HTTP '.$status.': '.$truncated;
    }

    /**
     * Clear the last error message.
     */
    private function clearLastError(): void
    {
        $this->lastError = null;
    }

    /**
     * Truncate a long text to the given limit.
     */
    private function truncateText(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit);
    }
}
