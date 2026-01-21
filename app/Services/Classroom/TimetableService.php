<?php

namespace App\Services\Classroom;

use App\Models\Classroom;
use App\Models\TimetableEntry;
use Illuminate\Support\Collection;

class TimetableService
{
    /**
     * Get the whole weekly timetable.
     *
     * @param Classroom $classroom
     * @return array
     */
    public function getWeeklyTimetable(Classroom $classroom): array
    {
        $entries = TimetableEntry::where('classroom_id', $classroom->id)
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');

        $timetable = [];
        for ($i = 0; $i <= 6; $i++) {
            $timetable[$i] = ($entries->get($i) ?? collect())->map(function (TimetableEntry $entry) {
                return [
                    'id' => $entry->id,
                    'start_time' => substr($entry->start_time, 0, 5),
                    'end_time' => substr($entry->end_time, 0, 5),
                    'subject' => $entry->subject,
                    'teacher' => $entry->teacher,
                ];
            })->values()->toArray();
        }

        return $timetable;
    }

    /**
     * Get timetable entries for a specific day.
     *
     * @param Classroom $classroom
     * @param int $dayOfWeek 0 (Sun) to 6 (Sat)
     * @return Collection
     */
    public function getEntriesForDay(Classroom $classroom, int $dayOfWeek): Collection
    {
        return TimetableEntry::where('classroom_id', $classroom->id)
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('start_time')
            ->get()
            ->map(function (TimetableEntry $entry) {
                return [
                    'id' => $entry->id,
                    'start_time' => substr($entry->start_time, 0, 5),
                    'end_time' => substr($entry->end_time, 0, 5),
                    'subject' => $entry->subject,
                    'teacher' => $entry->teacher,
                ];
            });
    }

    /**
     * Get the timetable image URL if it exists.
     *
     * @param Classroom $classroom
     * @return string|null
     */
    public function getTimetableImageUrl(Classroom $classroom): ?string
    {
        if (!$classroom->timetableFile) {
            return null;
        }

        return asset('storage/' . $classroom->timetableFile->path);
    }
}
