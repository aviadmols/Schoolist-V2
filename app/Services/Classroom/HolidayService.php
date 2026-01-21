<?php

namespace App\Services\Classroom;

use App\Models\Classroom;
use App\Models\Holiday;
use App\Models\HolidayTemplate;
use Illuminate\Support\Collection;

class HolidayService
{
    /**
     * Seed holidays for a new classroom from global templates.
     *
     * @param Classroom $classroom
     * @return void
     */
    public function seedFromTemplates(Classroom $classroom): void
    {
        $templates = HolidayTemplate::all();

        foreach ($templates as $template) {
            Holiday::create([
                'classroom_id' => $classroom->id,
                'name' => $template->name,
                'start_date' => $template->start_date,
                'end_date' => $template->end_date,
                'description' => $template->description,
            ]);
        }
    }

    /**
     * Get upcoming holidays for a classroom.
     *
     * @param Classroom $classroom
     * @return Collection
     */
    public function getUpcomingHolidays(Classroom $classroom): Collection
    {
        return Holiday::where('classroom_id', $classroom->id)
            ->where('end_date', '>=', now()->toDateString())
            ->orderBy('start_date', 'asc')
            ->get();
    }
}
