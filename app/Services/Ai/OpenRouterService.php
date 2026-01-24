<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OpenRouterService
{
    /** @var string */
    private const BASE_URL = 'https://openrouter.ai/api/v1';

    /** @var int */
    private const REQUEST_TIMEOUT_SECONDS = 120;

    /** @var int */
    private const ERROR_BODY_LIMIT = 500;

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
     * Request a timetable extraction completion.
     *
     * @return string|null
     */
    public function requestTimetableExtraction(
        string $token,
        string $model,
        string $prompt,
        string $imageMime,
        string $imageBase64
    ): ?string {
        $this->clearLastError();

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
            return null;
        }

        return $this->resolveResponseContent($response);
    }

    /**
     * Request a template HTML update.
     *
     * @return string|null
     */
    public function requestTemplateUpdate(string $token, string $model, string $prompt): ?string
    {
        $this->clearLastError();

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
            return null;
        }

        return $this->resolveResponseContent($response);
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
        ?string $imageBase64 = null
    ): ?string {
        $this->clearLastError();

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
            return null;
        }

        return $this->resolveResponseContent($response);
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
}
