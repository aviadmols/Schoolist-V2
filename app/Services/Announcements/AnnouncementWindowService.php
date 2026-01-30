<?php

namespace App\Services\Announcements;

use Carbon\CarbonImmutable;

class AnnouncementWindowService
{
    /** @var int */
    private const WINDOW_START_HOUR = 16;

    /**
     * Compute visibility window for an announcement.
     * Returns ['from' => CarbonImmutable, 'until' => CarbonImmutable]
     *
     * @param string|null $occursOnDate
     * @param string|null $endDate
     * @param bool $alwaysShow
     * @param int|null $dayOfWeek
     * @param string $timezone
     * @return array
     */
    public function getVisibilityWindow(?string $occursOnDate, ?string $endDate, bool $alwaysShow, ?int $dayOfWeek, string $timezone): array
    {
        if ($alwaysShow) {
            $from = CarbonImmutable::now($timezone)->subYears(10);
            $until = CarbonImmutable::now($timezone)->addYears(10);

            return [
                'from' => $from,
                'until' => $until,
            ];
        }

        $targetDate = $this->resolveTargetDate($occursOnDate, $dayOfWeek, $timezone);

        $from = $targetDate->subDay()->setTime(self::WINDOW_START_HOUR, 0, 0);
        $until = $targetDate->setTime(self::WINDOW_START_HOUR, 0, 0);

        if ($endDate) {
            $until = CarbonImmutable::parse($endDate, $timezone)->endOfDay();
        }

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
    private function resolveTargetDate(?string $occursOnDate, ?int $dayOfWeek, string $timezone): CarbonImmutable
    {
        if ($occursOnDate) {
            return CarbonImmutable::parse($occursOnDate, $timezone);
        }

        if ($dayOfWeek !== null) {
            $now = CarbonImmutable::now($timezone);
            // Get next occurrence of this day of week
            if ($now->dayOfWeek === $dayOfWeek && $now->hour < self::WINDOW_START_HOUR) {
                return $now->startOfDay();
            }
            return $now->next($dayOfWeek)->startOfDay();
        }

        return CarbonImmutable::today($timezone);
    }
}
