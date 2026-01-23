<?php

namespace App\Http\Controllers\Admin\Builder;

use App\Models\BuilderTemplate;
use App\Models\ClassLink;
use App\Models\Classroom;
use App\Models\ImportantContact;
use App\Models\User;
use App\Services\Announcements\AnnouncementFeedService;
use App\Services\Builder\TemplateRenderer;
use App\Services\Classroom\HolidayService;
use App\Services\Classroom\TimetableService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplatePreviewController
{
    /**
     * Render a template preview.
     */
    public function __invoke(
        BuilderTemplate $template,
        Request $request,
        TemplateRenderer $renderer,
        TimetableService $timetableService,
        AnnouncementFeedService $announcementService,
        HolidayService $holidayService
    ): View {
        $version = $request->string('version')->lower()->value() ?: 'draft';
        $mockData = $this->getMockData($template);
        $user = $this->getPreviewUser($request);
        $pageData = $template->key === 'classroom.page'
            ? $this->getClassroomPageData($request, $timetableService, $announcementService, $holidayService)
            : [];

        $data = array_merge([
            'user' => $user,
            'locale' => app()->getLocale(),
            'page' => $pageData,
        ], $mockData);

        $parts = $renderer->renderPreviewParts($template, $version, $data);

        return view('builder.screen', [
            'html' => $parts['html'],
            'css' => $parts['css'],
            'js' => $parts['js'],
        ]);
    }

    /**
     * Get mock data from the template.
     */
    private function getMockData(BuilderTemplate $template): array
    {
        if (is_array($template->mock_data_json)) {
            return $template->mock_data_json;
        }

        return [];
    }

    /**
     * Resolve the preview user from request input.
     */
    private function getPreviewUser(Request $request): ?User
    {
        $userId = $request->integer('preview_user_id');

        if ($userId) {
            return User::find($userId);
        }

        return $request->user();
    }

    /**
     * Build classroom page data for preview.
     */
    private function getClassroomPageData(
        Request $request,
        TimetableService $timetableService,
        AnnouncementFeedService $announcementService,
        HolidayService $holidayService
    ): array {
        $classroom = $this->getPreviewClassroom($request);

        if (!$classroom) {
            return [];
        }

        $today = Carbon::now($classroom->timezone);
        $selectedDay = (int) ($request->integer('preview_day') ?: $today->dayOfWeek);
        $timetable = $timetableService->getWeeklyTimetable($classroom);
        $announcements = $announcementService->getActiveFeed($classroom);
        $holidays = $holidayService->getUpcomingHolidays($classroom);
        $links = ClassLink::where('classroom_id', $classroom->id)
            ->orderBy('sort_order', 'asc')
            ->get()
            ->map(function (ClassLink $link): array {
                return [
                    'title' => $link->title,
                    'url' => $link->url,
                    'icon' => $link->icon,
                ];
            })
            ->values()
            ->all();
        $contacts = ImportantContact::where('classroom_id', $classroom->id)
            ->orderBy('first_name', 'asc')
            ->get()
            ->map(function (ImportantContact $contact): array {
                return [
                    'name' => trim($contact->first_name.' '.$contact->last_name),
                    'role' => $contact->role,
                    'phone' => $contact->phone,
                    'email' => $contact->email,
                ];
            })
            ->values()
            ->all();

        return [
            'school_year' => $this->getSchoolYearLabel($today),
            'classroom' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'grade_level' => $classroom->grade_level,
                'grade_number' => $classroom->grade_number,
                'city_name' => $classroom->city?->name,
                'school_name' => $classroom->school?->name,
            ],
            'selected_day' => $selectedDay,
            'day_labels' => ['א', 'ב', 'ג', 'ד', 'ה', 'ו', 'ש'],
            'timetable' => $timetable,
            'announcements' => $announcements,
            'events_today' => $this->filterHolidaysByRange($holidays, $today, $today),
            'events_week' => $this->filterHolidaysByRange($holidays, $today->copy()->startOfWeek(), $today->copy()->endOfWeek()),
            'links' => $links,
            'important_contacts' => $contacts,
            'weather_text' => '16-20° - מזג אוויר נוח.',
        ];
    }

    /**
     * Resolve the preview classroom from request input.
     */
    private function getPreviewClassroom(Request $request): ?Classroom
    {
        $classroomId = $request->integer('preview_classroom_id');

        if ($classroomId) {
            return Classroom::with(['city', 'school'])->find($classroomId);
        }

        return Classroom::with(['city', 'school'])->first();
    }

    /**
     * Build a school year label from a date.
     */
    private function getSchoolYearLabel(Carbon $date): string
    {
        $year = (int) $date->format('Y');
        $startYear = $date->month >= 9 ? $year : $year - 1;

        return $startYear.'-'.($startYear + 1);
    }

    /**
     * Map holidays into event rows within a range.
     *
     * @param \Illuminate\Support\Collection $holidays
     * @return array<int, array<string, string|null>>
     */
    private function filterHolidaysByRange($holidays, Carbon $from, Carbon $to): array
    {
        return $holidays
            ->filter(function ($holiday) use ($from, $to) {
                return $holiday->start_date && $holiday->start_date->between($from, $to);
            })
            ->map(function ($holiday): array {
                return [
                    'title' => $holiday->name,
                    'date' => $holiday->start_date?->format('d.m.Y'),
                    'time' => null,
                    'location' => $holiday->description,
                ];
            })
            ->values()
            ->all();
    }
}
