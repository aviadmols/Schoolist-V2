<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OpenRouterService
{
    /** @var string */
    private const BASE_URL = 'https://openrouter.ai/api/v1';

    /** @var int */
    private const REQUEST_TIMEOUT_SECONDS = 60;

    /**
     * Test the OpenRouter connection.
     */
    public function testConnection(string $token): bool
    {
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

        $response = $this->sendRequest($token, 'POST', self::BASE_URL.'/chat/completions', $payload);
        if (!$response->ok()) {
            return null;
        }

        return $response->json('choices.0.message.content');
    }

    /**
     * Request a template HTML update.
     *
     * @return string|null
     */
    public function requestTemplateUpdate(string $token, string $model, string $prompt): ?string
    {
        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ];

        $response = $this->sendRequest($token, 'POST', self::BASE_URL.'/chat/completions', $payload);
        if (!$response->ok()) {
            return null;
        }

        return $response->json('choices.0.message.content');
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

        $response = $this->sendRequest($token, 'POST', self::BASE_URL.'/chat/completions', $payload);
        if (!$response->ok()) {
            return null;
        }

        return $response->json('choices.0.message.content');
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
}
