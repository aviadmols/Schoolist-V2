<?php

namespace App\Services\Announcements;

use App\Models\Announcement;
use App\Models\Classroom;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AnnouncementFeedService
{
    /** @var AnnouncementWindowService */
    private AnnouncementWindowService $windowService;

    public function __construct(AnnouncementWindowService $windowService)
    {
        $this->windowService = $windowService;
    }

    /**
     * Get active announcements for a classroom.
     *
     * @param Classroom $classroom
     * @return Collection
     */
    public function getActiveFeed(Classroom $classroom): Collection
    {
        $now = Carbon::now($classroom->timezone);

        return Announcement::where('classroom_id', $classroom->id)
            ->with(['currentUserStatus'])
            ->get()
            ->filter(function (Announcement $announcement) use ($classroom, $now) {
                $window = $this->windowService->getVisibilityWindow(
                    $announcement->occurs_on_date?->toDateString(),
                    $announcement->end_date?->toDateString(),
                    (bool) $announcement->always_show,
                    $announcement->day_of_week,
                    $classroom->timezone
                );

                return $now->between($window['from'], $window['until']);
            })
            ->map(function (Announcement $announcement) {
                return [
                    'id' => $announcement->id,
                    'type' => $announcement->type,
                    'title' => $announcement->title,
                    'content' => $announcement->content,
                    'occurs_on_date' => $announcement->occurs_on_date?->toDateString(),
                    'end_date' => $announcement->end_date?->toDateString(),
                    'day_of_week' => $announcement->day_of_week,
                    'occurs_at_time' => $announcement->occurs_at_time,
                    'location' => $announcement->location,
                    'attachment_path' => $announcement->attachment_path,
                    'is_done' => $announcement->currentUserStatus?->done_at !== null,
                ];
            })
            ->values();
    }
}
