<?php

namespace App\Services\Announcements;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class AnnouncementWindowService
{
    /** @var int */
    private const WINDOW_START_HOUR = 16;

    /**
     * Compute visibility window for an announcement.
     * Returns ['from' => Carbon, 'until' => Carbon]
     *
     * @param string|null $occursOnDate
     * @param int|null $dayOfWeek
     * @param string $timezone
     * @return array
     */
    public function getVisibilityWindow(?string $occursOnDate, ?int $dayOfWeek, string $timezone): array
    {
        $targetDate = $this->resolveTargetDate($occursOnDate, $dayOfWeek, $timezone);

        $from = $targetDate->copy()->subDay()->setTime(self::WINDOW_START_HOUR, 0, 0);
        $until = $targetDate->copy()->setTime(self::WINDOW_START_HOUR, 0, 0);

        return [
            'from' => $from,
            'until' => $until,
        ];
    }

    /**
     * Resolve the specific date an announcement occurs.
     *
     * @param string|null $occursOnDate
     * @param int|null $dayOfWeek
     * @param string $timezone
     * @return Carbon
     */
    private function resolveTargetDate(?string $occursOnDate, ?int $dayOfWeek, string $timezone): Carbon
    {
        if ($occursOnDate) {
            return Carbon::parse($occursOnDate, $timezone);
        }

        if ($dayOfWeek !== null) {
            $now = Carbon::now($timezone);
            // Get next occurrence of this day of week
            if ($now->dayOfWeek === $dayOfWeek && $now->hour < self::WINDOW_START_HOUR) {
                return $now->startOfDay();
            }
            return $now->next($dayOfWeek)->startOfDay();
        }

        return Carbon::today($timezone);
    }
}
