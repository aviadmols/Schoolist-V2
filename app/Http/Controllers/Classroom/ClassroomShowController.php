<?php

namespace App\Http\Controllers\Classroom;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\ChildContact;
use App\Models\ClassLink;
use App\Models\Classroom;
use App\Models\ImportantContact;
use App\Models\User;
use App\Services\Announcements\AnnouncementFeedService;
use App\Services\Builder\TemplateRenderer;
use App\Services\Classroom\HolidayService;
use App\Services\Classroom\TimetableService;
use App\Services\Weather\WeatherService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ClassroomShowController extends Controller
{
    /**
     * Show the classroom page (builder template or Inertia fallback).
     */
    public function show(Request $request, Classroom $classroom)
    {
        $classroom->load([
            'city',
            'school',
            'children.contacts',
            'users',
            'weatherSetting',
            'links',
            'importantContacts',
        ]);

        $user = auth()->user();
        $today = Carbon::now($classroom->timezone);
        $canManage = false;

        if ($user) {
            $canManage = $user->role === 'site_admin'
                || $classroom->users
                    ->where('id', $user->id)
                    ->filter(function (User $u) {
                        return in_array($u->pivot->role ?? null, ['owner', 'admin']);
                    })
                    ->isNotEmpty();
        }

        $baseDay = $today->hour >= 16 ? ($today->dayOfWeek + 1) % 7 : $today->dayOfWeek;
        $selectedDay = (int) $request->query('day', $baseDay);

        $activeDays = $classroom->active_days ?? [];
        if (!empty($activeDays) && !in_array($selectedDay, $activeDays)) {
            for ($i = 1; $i <= 7; $i++) {
                $nextDay = ($selectedDay + $i) % 7;
                if (in_array($nextDay, $activeDays)) {
                    $selectedDay = $nextDay;
                    break;
                }
            }
        }

        $timetableService = app(TimetableService::class);
        $announcementService = app(AnnouncementFeedService::class);
        $holidayService = app(HolidayService::class);
        $weatherService = app(WeatherService::class);
        $holidays = $holidayService->getUpcomingHolidays($classroom);
        $dayLabels = ['א', 'ב', 'ג', 'ד', 'ה', 'ו', 'ש'];
        $dayNames = ['ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת'];

        $getTimeBasedGreeting = function (int $hour): string {
            if ($hour >= 5 && $hour < 12) {
                return 'בוקר טוב';
            }
            if ($hour >= 12 && $hour < 16) {
                return 'צוהריים טובים';
            }
            if ($hour >= 16 && $hour < 19) {
                return 'אחר צהריים טובים';
            }
            if ($hour >= 19 && $hour < 23) {
                return 'ערב טוב';
            }
            return 'לילה טוב';
        };
        $greeting = $getTimeBasedGreeting($today->hour);

        $mapHolidays = function (Carbon $from, Carbon $to) use ($holidays): array {
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
        };

        $formatDate = function (?string $date) use ($classroom): ?string {
            if (!$date) {
                return null;
            }
            return Carbon::parse($date, $classroom->timezone)->format('d.m.Y');
        };
        $formatTime = function ($time): ?string {
            if (!$time) {
                return null;
            }
            return substr((string) $time, 0, 5);
        };

        $weekStart = $today->copy()->startOfWeek();
        $weekEnd = $today->copy()->endOfWeek();

        $pageData = Cache::remember("classroom.page.data.{$classroom->id}", 300, function () use (
            $classroom,
            $today,
            $timetableService,
            $announcementService,
            $weatherService,
            $greeting,
            $dayLabels,
            $dayNames,
            $formatDate,
            $formatTime,
            $mapHolidays,
            $weekStart,
            $weekEnd,
            $holidays
        ) {
            $allAnnouncements = $announcementService->getActiveFeed($classroom);

            $announcements = $allAnnouncements
                ->filter(fn (array $a) => ($a['type'] ?? '') === 'message')
                ->filter(function (array $a) use ($today, $classroom): bool {
                    if (empty($a['occurs_on_date'])) {
                        return false;
                    }
                    $date = Carbon::parse($a['occurs_on_date'], $classroom->timezone);
                    return $date->isSameDay($today) || $date->greaterThan($today);
                })
                ->map(function (array $a) use ($formatDate, $formatTime): array {
                    return [
                        'id' => $a['id'] ?? null,
                        'type' => $a['type'] ?? 'message',
                        'title' => $a['title'] ?? '',
                        'content' => $a['content'] ?? '',
                        'date' => $formatDate($a['occurs_on_date'] ?? null),
                        'time' => $formatTime($a['occurs_at_time'] ?? null),
                        'location' => $a['location'] ?? '',
                        'is_done' => $a['is_done'] ?? false,
                        'created_by' => $a['created_by'] ?? null,
                    ];
                })
                ->values()
                ->all();

            $events = $allAnnouncements
                ->filter(fn (array $a) => ($a['type'] ?? '') === 'event')
                ->map(function (array $a) use ($formatDate, $formatTime): array {
                    return [
                        'id' => $a['id'] ?? null,
                        'type' => $a['type'] ?? 'event',
                        'title' => $a['title'] ?? '',
                        'content' => $a['content'] ?? '',
                        'date' => $formatDate($a['occurs_on_date'] ?? null),
                        'time' => $formatTime($a['occurs_at_time'] ?? null),
                        'location' => $a['location'] ?? '',
                        'created_by' => $a['created_by'] ?? null,
                    ];
                })
                ->values()
                ->all();

            $allChildren = $classroom->children->sortBy('name');

            $children = $allChildren->map(function (Child $child): array {
                $contacts = $child->contacts
                    ->sortBy('name')
                    ->map(function (ChildContact $contact): array {
                        return [
                            'name' => $contact->name,
                            'phone' => $contact->phone,
                            'relation' => $contact->relation,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'id' => $child->id,
                    'name' => $child->name,
                    'birth_date' => $child->birth_date?->format('d.m.Y'),
                    'contacts' => $contacts,
                ];
            })->values()->all();

            $upcomingBirthdays = $allChildren
                ->whereNotNull('birth_date')
                ->map(function (Child $child) use ($today): ?array {
                    $birthDate = $child->birth_date;
                    if (!$birthDate) {
                        return null;
                    }
                    $thisYearBirthday = Carbon::create($today->year, $birthDate->month, $birthDate->day, 0, 0, 0, $today->timezone);
                    $nextYearBirthday = Carbon::create($today->year + 1, $birthDate->month, $birthDate->day, 0, 0, 0, $today->timezone);
                    $weekEnd = $today->copy()->addWeek();
                    if ($thisYearBirthday->between($today, $weekEnd)) {
                        return ['name' => $child->name, 'date' => $thisYearBirthday->format('d.m.Y'), 'days_until' => $today->diffInDays($thisYearBirthday, false)];
                    }
                    if ($nextYearBirthday->between($today, $weekEnd)) {
                        return ['name' => $child->name, 'date' => $nextYearBirthday->format('d.m.Y'), 'days_until' => $today->diffInDays($nextYearBirthday, false)];
                    }
                    return null;
                })
                ->filter()
                ->sortBy('days_until')
                ->values()
                ->all();

            $weatherData = $weatherService->getWeatherForClassroom($classroom);

            return [
                'school_year' => ($today->month >= 9 ? $today->year : $today->year - 1).'-'.(($today->month >= 9 ? $today->year : $today->year - 1) + 1),
                'classroom' => [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'grade_level' => $classroom->grade_level,
                    'grade_number' => $classroom->grade_number,
                    'city_name' => $classroom->city?->name,
                    'school_name' => $classroom->school?->name,
                    'allow_member_posting' => $classroom->allow_member_posting,
                ],
                'day_labels' => $dayLabels,
                'day_names' => $dayNames,
                'timetable' => $timetableService->getWeeklyTimetable($classroom),
                'announcements' => $announcements,
                'events' => $events,
                'events_today' => $mapHolidays($today, $today),
                'events_week' => $mapHolidays($weekStart, $weekEnd),
                'links' => $classroom->links
                    ->sortBy('sort_order')
                    ->map(function (ClassLink $link): array {
                        $fileUrl = $link->file_path ? Storage::disk('public')->url($link->file_path) : null;
                        $linkUrl = $link->url ?: $fileUrl;
                        return [
                            'title' => $link->title,
                            'url' => $link->url,
                            'link_url' => $linkUrl,
                            'file_url' => $fileUrl,
                            'category' => $link->category,
                            'icon' => $link->icon,
                        ];
                    })
                    ->values()
                    ->all(),
                'holidays' => $holidays
                    ->filter(fn ($h) => $h->end_date && $h->end_date->greaterThanOrEqualTo($today))
                    ->sortBy(fn ($h) => $h->start_date?->timestamp ?? 0)
                    ->map(function ($holiday): array {
                        return [
                            'name' => $holiday->name,
                            'start_date' => $holiday->start_date?->format('d.m.Y'),
                            'end_date' => $holiday->end_date?->format('d.m.Y'),
                            'description' => $holiday->description,
                            'has_kitan' => $holiday->has_kitan ?? false,
                        ];
                    })
                    ->values()
                    ->all(),
                'important_contacts' => $classroom->importantContacts
                    ->sortBy('first_name')
                    ->map(function (ImportantContact $contact): array {
                        return [
                            'name' => trim($contact->first_name.' '.$contact->last_name),
                            'role' => $contact->role,
                            'phone' => $contact->phone,
                            'email' => $contact->email,
                        ];
                    })
                    ->values()
                    ->all(),
                'children' => $children,
                'weather' => $weatherData,
                'greeting' => $greeting,
                'upcoming_birthdays' => $upcomingBirthdays,
                'classroom_admins' => $classroom->users
                    ->filter(function (User $u) use ($classroom): bool {
                        $byRole = in_array($u->pivot->role ?? null, ['owner', 'admin']);
                        $byId = in_array($u->id, $classroom->classroom_admins ?? []);
                        return $byRole || $byId;
                    })
                    ->map(fn (User $admin): array => ['id' => $admin->id, 'name' => $admin->name, 'phone' => $admin->phone])
                    ->values()
                    ->all(),
                'timetable_image' => $timetableService->getTimetableImageUrl($classroom),
                'share_link' => url("/class/{$classroom->id}"),
            ];
        });

        $pageData['current_user'] = $user ? ['id' => $user->id, 'name' => $user->name, 'phone' => $user->phone] : null;
        $pageData['can_manage'] = $canManage;
        $pageData['admin_edit_url'] = $canManage ? url("/admin/classrooms/{$classroom->id}/edit") : null;
        $pageData['selected_day'] = $selectedDay;
        $weekStart = $today->copy()->startOfWeek();
        $pageData['selected_date'] = $weekStart->copy()->addDays($selectedDay)->format('Y-m-d');
        $pageData['week_dates'] = array_map(fn ($d) => $weekStart->copy()->addDays($d)->format('Y-m-d'), range(0, 6));

        $overrideParts = app(TemplateRenderer::class)->renderPublishedPartsByKey('classroom.page', [
            'user' => auth()->user(),
            'classroom' => $classroom,
            'locale' => app()->getLocale(),
            'page' => $pageData,
        ]);

        if ($overrideParts) {
            return response()->view('builder.screen', [
                'html' => $overrideParts['html'],
                'css' => $overrideParts['css'],
                'js' => $overrideParts['js'],
            ]);
        }

        return Inertia::render('Dashboard', $pageData);
    }
}
