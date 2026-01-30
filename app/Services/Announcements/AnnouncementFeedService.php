<?php

namespace App\Services\Announcements;

use App\Models\Announcement;
use App\Models\AnnouncementUserStatus;
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
        $userId = auth()->id();
        $activeFeedDays = (int) config('announcements.active_feed_days', 14);
        $minDate = $now->copy()->subDays($activeFeedDays)->toDateString();

        $announcements = Announcement::where('classroom_id', $classroom->id)
            ->where(function ($query) use ($minDate) {
                $query->whereNull('occurs_on_date')
                    ->orWhere('always_show', true)
                    ->orWhereNotNull('day_of_week')
                    ->orWhere('occurs_on_date', '>=', $minDate);
            })
            ->where(function ($query) use ($minDate) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $minDate);
            })
            ->with('creator')
            ->get();

        $userStatuses = collect();
        if ($userId && $announcements->isNotEmpty()) {
            $userStatuses = AnnouncementUserStatus::where('user_id', $userId)
                ->whereIn('announcement_id', $announcements->pluck('id'))
                ->get()
                ->keyBy('announcement_id');
        }

        return $announcements
            ->filter(fn (Announcement $announcement) => $this->isAnnouncementVisible($announcement, $classroom, $now))
            ->map(fn (Announcement $announcement) => $this->formatAnnouncement($announcement, $userStatuses))
            ->values();
    }

    /**
     * Check whether the announcement should be visible now.
     */
    private function isAnnouncementVisible(Announcement $announcement, Classroom $classroom, Carbon $now): bool
    {
        // TODO: If this becomes a bottleneck, consider moving parts of the window logic into SQL.
        $window = $this->windowService->getVisibilityWindow(
            $announcement->occurs_on_date?->toDateString(),
            $announcement->end_date?->toDateString(),
            (bool) $announcement->always_show,
            $announcement->day_of_week,
            $classroom->timezone
        );

        return $now->between($window['from'], $window['until']);
    }

    /**
     * Format an announcement for the API payload.
     */
    private function formatAnnouncement(Announcement $announcement, Collection $userStatuses): array
    {
        $createdBy = $announcement->creator?->name
            ?? $announcement->creator?->phone
            ?? 'משתמש';

        $userStatus = $userStatuses->get($announcement->id);

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
            'is_done' => $userStatus?->done_at !== null,
            'created_by' => $createdBy,
        ];
    }
}
