<?php

namespace App\Http\Controllers\Classroom;

use App\Http\Controllers\Controller;
use App\Services\Announcements\AnnouncementFeedService;
use App\Services\Classroom\TimetableService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class DashboardController extends Controller
{
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
        $dayOfWeek = $request->query('day', Carbon::now($classroom->timezone)->dayOfWeek);

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
            'timetable' => $this->timetableService->getWeeklyTimetable($classroom), // Get whole week
            'announcements' => $this->announcementService->getActiveFeed($classroom),
            'timetable_image' => $this->timetableService->getTimetableImageUrl($classroom),
        ]);
    }
}
