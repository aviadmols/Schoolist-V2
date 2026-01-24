<?php

namespace App\Services\Classroom;

use App\Models\AiSetting;
use App\Models\Classroom;
use App\Models\TimetableEntry;
use App\Services\Ai\OpenRouterService;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TimetableOcrService
{
    /** @var array<string, int> */
    private const DAY_MAP = [
        'sunday' => 0,
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
    ];

    /** @var int */
    private const DEFAULT_SORT_INCREMENT = 10;

    /** @var OpenRouterService */
    private OpenRouterService $openRouterService;

    /**
     * Create the service.
     */
    public function __construct(OpenRouterService $openRouterService)
    {
        $this->openRouterService = $openRouterService;
    }

    /**
     * Extract timetable entries from the classroom image and persist them.
     */
    public function extractAndSaveTimetable(Classroom $classroom, AiSetting $setting): void
    {
        $imagePath = (string) $classroom->timetable_image_path;
        if ($imagePath === '') {
            throw new RuntimeException('Timetable image is missing.');
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($imagePath)) {
            throw new RuntimeException('Timetable image file not found.');
        }

        $imageContent = $disk->get($imagePath);
        if ($imageContent === false) {
            throw new RuntimeException('Failed to read timetable image.');
        }

        $imageMime = $disk->mimeType($imagePath) ?: 'image/jpeg';
        $imageBase64 = base64_encode($imageContent);

        $responseText = $this->openRouterService->requestTimetableExtraction(
            (string) $setting->token,
            (string) $setting->model,
            (string) $setting->timetable_prompt,
            $imageMime,
            $imageBase64
        );

        if (!$responseText) {
            throw new RuntimeException('OpenRouter returned an empty response.');
        }

        $parsed = $this->parseTimetableResponse($responseText);
        $this->storeTimetableEntries($classroom, $parsed);
    }

    /**
     * Parse the timetable response into a schedule payload.
     *
     * @return array<string, array<int, array<string, string|null>>>
     */
    private function parseTimetableResponse(string $responseText): array
    {
        $json = $this->extractJson($responseText);
        $payload = json_decode($json, true);

        if (!is_array($payload) || !isset($payload['schedule']) || !is_array($payload['schedule'])) {
            throw new RuntimeException('Invalid timetable response format.');
        }

        $schedule = [];
        foreach (self::DAY_MAP as $day => $dayIndex) {
            $entries = $payload['schedule'][$day] ?? [];
            if (!is_array($entries)) {
                $entries = [];
            }

            $schedule[$day] = [];
            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $schedule[$day][] = [
                    'time' => $this->normalizeTime((string) ($entry['time'] ?? '')),
                    'subject' => trim((string) ($entry['subject'] ?? '')),
                    'teacher' => $this->normalizeOptionalValue($entry['teacher'] ?? null),
                    'room' => $this->normalizeOptionalValue($entry['room'] ?? null),
                ];
            }
        }

        return $schedule;
    }

    /**
     * Store timetable entries for the classroom.
     *
     * @param array<string, array<int, array<string, string|null>>> $schedule
     */
    private function storeTimetableEntries(Classroom $classroom, array $schedule): void
    {
        TimetableEntry::query()
            ->where('classroom_id', $classroom->id)
            ->delete();

        foreach (self::DAY_MAP as $day => $dayIndex) {
            $entries = $schedule[$day] ?? [];
            $sortOrder = self::DEFAULT_SORT_INCREMENT;

            foreach ($entries as $entry) {
                if (!$entry['subject'] || !$entry['time']) {
                    continue;
                }

                [$startTime, $endTime] = $this->splitTimeRange($entry['time']);

                TimetableEntry::create([
                    'classroom_id' => $classroom->id,
                    'day_of_week' => $dayIndex,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'subject' => $entry['subject'],
                    'teacher' => $entry['teacher'],
                    'room' => $entry['room'],
                    'special_message' => null,
                    'sort_order' => $sortOrder,
                ]);

                $sortOrder += self::DEFAULT_SORT_INCREMENT;
            }
        }
    }

    /**
     * Extract JSON from the response text.
     */
    private function extractJson(string $responseText): string
    {
        $trimmed = trim($responseText);
        if ($trimmed !== '' && $trimmed[0] === '{') {
            return $trimmed;
        }

        preg_match('/\{.*\}/s', $responseText, $matches);

        return $matches[0] ?? $responseText;
    }

    /**
     * Normalize time values into HH:MM or HH:MM-HH:MM.
     */
    private function normalizeTime(string $value): string
    {
        return trim($value);
    }

    /**
     * Normalize optional string values.
     */
    private function normalizeOptionalValue(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Split a time range into start and end times.
     *
     * @return array{0: string, 1: string}
     */
    private function splitTimeRange(string $value): array
    {
        $parts = array_map('trim', explode('-', $value));
        if (count($parts) >= 2) {
            return [$parts[0], $parts[1]];
        }

        return [$value, $value];
    }
}
