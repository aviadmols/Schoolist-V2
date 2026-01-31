<?php

namespace App\Http\Controllers\Classroom;

use App\Http\Controllers\Controller;
use App\Services\Announcements\AnnouncementFeedService;
use App\Services\Classroom\TimetableService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** @var int Dashboard payload cache TTL (seconds). */
    private const DASHBOARD_CACHE_TTL = 120;

    /** @var TimetableService */
    private TimetableService $timetableService;

    /** @var AnnouncementFeedService */
    private AnnouncementFeedService $announcementService;

    public function __construct(TimetableService $timetableService, AnnouncementFeedService $announcementService)
    {
        $this->timetableService = $timetableService;
        $this->announcementService = $announcementService;
    }

    /**
     * Show the classroom dashboard.
     */
    public function __invoke(Request $request): Response
    {
        $classroom = $request->attributes->get('current_classroom');
        $today = Carbon::now($classroom->timezone);
        $dayOfWeek = $request->query('day', $today->dayOfWeek);

        $cacheKey = sprintf('dashboard.classroom.%s.%s', $classroom->id, $today->format('Y-m-d'));
        $cached = Cache::remember($cacheKey, self::DASHBOARD_CACHE_TTL, function () use ($classroom) {
            return [
                'timetable' => $this->timetableService->getWeeklyTimetable($classroom),
                'announcements' => $this->announcementService->getActiveFeed($classroom)->values()->all(),
                'timetable_image' => $this->timetableService->getTimetableImageUrl($classroom),
            ];
        });

        return Inertia::render('Dashboard', [
            'classroom' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'grade_level' => $classroom->grade_level,
                'grade_number' => $classroom->grade_number,
                'city_name' => $classroom->city?->name,
                'school_name' => $classroom->school?->name,
            ],
            'selected_day' => (int) $dayOfWeek,
            'timetable' => $cached['timetable'],
            'announcements' => $cached['announcements'],
            'timetable_image' => $cached['timetable_image'],
        ]);
    }
}
