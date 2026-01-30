<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GetLoginPageController;
use App\Http\Controllers\Auth\GetProfilePageController;
use App\Http\Controllers\Auth\GetOtpLoginPageController;
use App\Http\Controllers\Auth\QlinkController;
use App\Http\Controllers\Admin\Builder\TemplatePreviewController;
use App\Http\Controllers\Public\GetLandingPageController;
use App\Models\ClassLink;
use App\Models\ImportantContact;
use App\Services\Announcements\AnnouncementFeedService;
use App\Services\Builder\TemplateRenderer;
use App\Services\Classroom\HolidayService;
use App\Services\Classroom\TimetableService;
use App\Services\Weather\WeatherService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

Route::get('/', GetLandingPageController::class)->name('landing');

Route::get('/login', GetLoginPageController::class)->name('login');
Route::post('/login', \App\Http\Controllers\Auth\LoginController::class);
Route::get('/auth/code', GetOtpLoginPageController::class)->name('auth.code');

// Temporary setup route - DELETE AFTER USE
Route::get('/setup-admin', function () {
    $user = \App\Models\User::updateOrCreate(
        ['email' => 'admin@schoolist.co.il'],
        [
            'name' => 'Admin',
            'phone' => '0500000000',
            'password' => \Illuminate\Support\Facades\Hash::make('12345678'),
            'role' => 'site_admin'
        ]
    );
    return 'Admin user created successfully! Email: ' . $user->email;
});

Route::prefix('auth')->group(function () {
    Route::post('/otp/request', \App\Http\Controllers\Auth\RequestOtpController::class)
        ->middleware('throttle:otp')
        ->name('auth.otp.request');

    Route::post('/otp/verify', \App\Http\Controllers\Auth\VerifyOtpController::class)
        ->name('auth.otp.verify');

    Route::post('/register', \App\Http\Controllers\Auth\RegisterController::class)
        ->name('auth.register');
});

Route::get('/qlink/{token}', [QlinkController::class, 'show'])->name('qlink.show');
Route::post('/qlink/request', [QlinkController::class, 'requestOtp'])->name('qlink.request');
Route::post('/qlink/verify', [QlinkController::class, 'verifyOtp'])->name('qlink.verify');
Route::post('/qlink/register', [QlinkController::class, 'register'])->name('qlink.register');
Route::post('/qlink/join', [QlinkController::class, 'join'])->name('qlink.join');
Route::post('/qlink/auto', [QlinkController::class, 'autoLogin'])->name('qlink.auto');

Route::get('/class/{classroom}', function (\App\Models\Classroom $classroom) {
    // Eager load all relationships upfront to prevent N+1 queries
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
    
    // User is already authenticated and has access (checked by middleware)
    $today = Carbon::now($classroom->timezone);
    $canManage = false;

    if ($user) {
        // Use already loaded users relationship instead of querying again
        $canManage = $user->role === 'site_admin'
            || $classroom->users
                ->where('id', $user->id)
                ->filter(function (\App\Models\User $u) {
                    return in_array($u->pivot->role ?? null, ['owner', 'admin']);
                })
                ->isNotEmpty();
    }
    
    // Advanced day logic: if hour >= 16:00, show tomorrow
    $baseDay = $today->hour >= 16 ? ($today->dayOfWeek + 1) % 7 : $today->dayOfWeek;
    $selectedDay = (int) request()->query('day', $baseDay);
    
    // Check if selected day is active, if not find next active day
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
    
    // Get time-based greeting
    $getTimeBasedGreeting = function (int $hour): string {
        if ($hour >= 5 && $hour < 12) {
            return 'בוקר טוב';
        } elseif ($hour >= 12 && $hour < 16) {
            return 'צוהריים טובים';
        } elseif ($hour >= 16 && $hour < 19) {
            return 'אחר צהריים טובים';
        } elseif ($hour >= 19 && $hour < 23) {
            return 'ערב טוב';
        } else {
            return 'לילה טוב';
        }
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

    // Cache key without selectedDay since it doesn't affect the data, only display
    $pageData = Cache::remember("classroom.page.data.{$classroom->id}", 300, function () use ($classroom, $today, $timetableService, $announcementService, $weatherService, $greeting, $dayLabels, $dayNames, $formatDate, $formatTime, $mapHolidays, $weekStart, $weekEnd, $holidays) {
        // Get all announcements once and filter by type
        $allAnnouncements = $announcementService->getActiveFeed($classroom);
        
        // Process announcements (messages)
        $announcements = $allAnnouncements
            ->filter(fn (array $announcement) => ($announcement['type'] ?? '') === 'message')
            ->filter(function (array $announcement) use ($today, $classroom): bool {
                if (empty($announcement['occurs_on_date'])) return false;
                $date = Carbon::parse($announcement['occurs_on_date'], $classroom->timezone);
                return $date->isSameDay($today) || $date->greaterThan($today);
            })
            ->map(function (array $announcement) use ($formatDate, $formatTime): array {
                return [
                    'id' => $announcement['id'] ?? null,
                    'type' => $announcement['type'] ?? 'message',
                    'title' => $announcement['title'] ?? '',
                    'content' => $announcement['content'] ?? '',
                    'date' => $formatDate($announcement['occurs_on_date'] ?? null),
                    'time' => $formatTime($announcement['occurs_at_time'] ?? null),
                    'location' => $announcement['location'] ?? '',
                    'is_done' => $announcement['is_done'] ?? false,
                    'created_by' => $announcement['created_by'] ?? null,
                ];
            })
            ->values()
            ->all();
        
        // Process events
        $events = $allAnnouncements
            ->filter(fn (array $announcement) => ($announcement['type'] ?? '') === 'event')
            ->map(function (array $announcement) use ($formatDate, $formatTime): array {
                return [
                    'id' => $announcement['id'] ?? null,
                    'type' => $announcement['type'] ?? 'event',
                    'title' => $announcement['title'] ?? '',
                    'content' => $announcement['content'] ?? '',
                    'date' => $formatDate($announcement['occurs_on_date'] ?? null),
                    'time' => $formatTime($announcement['occurs_at_time'] ?? null),
                    'location' => $announcement['location'] ?? '',
                    'created_by' => $announcement['created_by'] ?? null,
                ];
            })
            ->values()
            ->all();

        // Use already eager-loaded children relationship
        $allChildren = $classroom->children
            ->sortBy('name');
        
        // Process children
        $children = $allChildren->map(function (\App\Models\Child $child): array {
            $contacts = $child->contacts
                ->sortBy('name')
                ->map(function (\App\Models\ChildContact $contact): array {
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
        
        // Process upcoming birthdays from the same children collection
        $upcomingBirthdays = $allChildren
            ->whereNotNull('birth_date')
            ->map(function (\App\Models\Child $child) use ($today): ?array {
                $birthDate = $child->birth_date;
                if (!$birthDate) return null;
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

        // Get weather data once
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
                ->map(function (\App\Models\ClassLink $link): array {
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
                ->filter(function ($holiday) use ($today) {
                    return $holiday->end_date && $holiday->end_date->greaterThanOrEqualTo($today);
                })
                ->sortBy(function ($holiday) {
                    return $holiday->start_date?->timestamp ?? 0;
                })
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
                ->map(function (\App\Models\ImportantContact $contact): array {
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
                ->filter(function (\App\Models\User $user) use ($classroom): bool {
                    $isAdminByRole = in_array($user->pivot->role ?? null, ['owner', 'admin']);
                    $isAdminById = in_array($user->id, $classroom->classroom_admins ?? []);
                    return $isAdminByRole || $isAdminById;
                })
                ->map(function (\App\Models\User $admin): array {
                    return ['id' => $admin->id, 'name' => $admin->name, 'phone' => $admin->phone];
                })
                ->values()
                ->all(),
            'timetable_image' => $timetableService->getTimetableImageUrl($classroom),
            'share_link' => url("/class/{$classroom->id}"),
        ];
    });

    // Add user-specific data after cache (not cached since it's user-specific)
    $pageData['current_user'] = $user ? ['id' => $user->id, 'name' => $user->name, 'phone' => $user->phone] : null;
    $pageData['can_manage'] = $canManage;
    $pageData['admin_edit_url'] = $canManage ? url("/admin/classrooms/{$classroom->id}/edit") : null;
    $pageData['selected_day'] = $selectedDay; // Add selected day after cache

    // Render template - this is expensive but necessary for dynamic content
    // Note: TemplateRenderer has its own internal caching for resolved HTML
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

    return \Inertia\Inertia::render('Dashboard', $pageData);
})->middleware(['auth', 'classroom.access'])->name('classroom.show');

Route::post('/class/{classroom}/ai-analyze', [\App\Http\Controllers\Classroom\FrontendAiAddController::class, 'analyze'])
    ->middleware(['auth', 'classroom.access'])
    ->name('classroom.ai.analyze');
Route::post('/class/{classroom}/ai-store', [\App\Http\Controllers\Classroom\FrontendAiAddController::class, 'store'])
    ->middleware(['auth', 'classroom.access'])
    ->name('classroom.ai.store');

Route::middleware('auth')->group(function () {
    Route::get('/me', GetProfilePageController::class)->name('profile.show');
    Route::get('/dashboard', \App\Http\Controllers\Classroom\DashboardController::class)
        ->middleware('classroom.context')
        ->name('dashboard');

    Route::prefix('classrooms')->group(function () {
        Route::get('/', [\App\Http\Controllers\Classroom\ClassroomController::class, 'index'])->name('classroom.index');
        Route::get('/create', [\App\Http\Controllers\Classroom\ClassroomController::class, 'create'])->name('classroom.create');
        Route::post('/', [\App\Http\Controllers\Classroom\ClassroomController::class, 'store'])->name('classroom.store');
        Route::post('/join', \App\Http\Controllers\Classroom\JoinClassroomController::class)->name('classroom.join');
        Route::post('/{classroom}/switch', [\App\Http\Controllers\Classroom\ClassroomController::class, 'switch'])->name('classroom.switch');
        
        // Link Claiming
        Route::get('/claim', [\App\Http\Controllers\Classroom\LinkClaimController::class, 'view'])->name('classroom.claim.view');
        Route::post('/claim', [\App\Http\Controllers\Classroom\LinkClaimController::class, 'claim'])->name('classroom.claim');

        // Membership management
        Route::post('/{classroom}/members/{user}/role', [\App\Http\Controllers\Classroom\MembershipController::class, 'updateRole'])->name('classroom.membership.update');
        Route::delete('/{classroom}/members/{user}', [\App\Http\Controllers\Classroom\MembershipController::class, 'remove'])->name('classroom.membership.remove');

        // File management
        Route::post('/{classroom}/files', [\App\Http\Controllers\Classroom\FileController::class, 'upload'])->name('classroom.files.upload');
        Route::get('/{classroom}/files/{file}/download', [\App\Http\Controllers\Classroom\FileController::class, 'download'])->name('classroom.files.download');
        Route::delete('/{classroom}/files/{file}', [\App\Http\Controllers\Classroom\FileController::class, 'destroy'])->name('classroom.files.destroy');

        // Useful Links
        Route::get('/links', [\App\Http\Controllers\Classroom\LinkController::class, 'index'])->name('classroom.links.index');
        Route::post('/links', [\App\Http\Controllers\Classroom\LinkController::class, 'store'])->name('classroom.links.store');
        Route::put('/links/{link}', [\App\Http\Controllers\Classroom\LinkController::class, 'update'])->name('classroom.links.update');
        Route::delete('/links/{link}', [\App\Http\Controllers\Classroom\LinkController::class, 'destroy'])->name('classroom.links.destroy');

        // WhatsApp Updates
        Route::get('/whatsapp', [\App\Http\Controllers\Classroom\LinkController::class, 'index'])->name('classroom.whatsapp.index');
        Route::post('/whatsapp', [\App\Http\Controllers\Classroom\LinkController::class, 'store'])->name('classroom.whatsapp.store');
        Route::put('/whatsapp/{link}', [\App\Http\Controllers\Classroom\LinkController::class, 'update'])->name('classroom.whatsapp.update');
        Route::delete('/whatsapp/{link}', [\App\Http\Controllers\Classroom\LinkController::class, 'destroy'])->name('classroom.whatsapp.destroy');

        // Directory
        Route::get('/directory', [\App\Http\Controllers\Classroom\DirectoryController::class, 'index'])->name('classroom.directory.index');
        Route::post('/directory', [\App\Http\Controllers\Classroom\DirectoryController::class, 'store'])->name('classroom.directory.store');
        Route::put('/directory/{child}', [\App\Http\Controllers\Classroom\DirectoryController::class, 'update'])->name('classroom.directory.update');
        Route::delete('/directory/{child}', [\App\Http\Controllers\Classroom\DirectoryController::class, 'destroy'])->name('classroom.directory.destroy');

        // Important Contacts
        Route::get('/important-contacts', [\App\Http\Controllers\Classroom\ImportantContactController::class, 'index'])->name('classroom.important_contacts.index');
        Route::post('/important-contacts', [\App\Http\Controllers\Classroom\ImportantContactController::class, 'store'])->name('classroom.important_contacts.store');
        Route::put('/important-contacts/{contact}', [\App\Http\Controllers\Classroom\ImportantContactController::class, 'update'])->name('classroom.important_contacts.update');
        Route::delete('/important-contacts/{contact}', [\App\Http\Controllers\Classroom\ImportantContactController::class, 'destroy'])->name('classroom.important_contacts.destroy');

        // Private Items
        Route::get('/private', [\App\Http\Controllers\Classroom\PrivateItemController::class, 'index'])->name('classroom.private.index');
        Route::post('/private', [\App\Http\Controllers\Classroom\PrivateItemController::class, 'store'])->name('classroom.private.store');
        Route::put('/private/{privateItem}', [\App\Http\Controllers\Classroom\PrivateItemController::class, 'update'])->name('classroom.private.update');
        Route::delete('/private/{privateItem}', [\App\Http\Controllers\Classroom\PrivateItemController::class, 'destroy'])->name('classroom.private.destroy');
    });

    // Scoped by classroom.context middleware
    Route::middleware('classroom.context')->group(function () {
        Route::get('/announcements', [\App\Http\Controllers\Classroom\AnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('/announcements', [\App\Http\Controllers\Classroom\AnnouncementController::class, 'store'])->name('announcements.store');
        Route::post('/announcements/{announcement}/done', [\App\Http\Controllers\Classroom\AnnouncementController::class, 'toggleDone'])->name('announcements.done');
        Route::delete('/announcements/{announcement}', [\App\Http\Controllers\Classroom\AnnouncementController::class, 'destroy'])->name('announcements.destroy');

        Route::get('/holidays', [\App\Http\Controllers\Classroom\HolidayController::class, 'index'])->name('holidays.index');
        Route::post('/holidays', [\App\Http\Controllers\Classroom\HolidayController::class, 'store'])->name('holidays.store');
        Route::put('/holidays/{holiday}', [\App\Http\Controllers\Classroom\HolidayController::class, 'update'])->name('holidays.update');
        Route::delete('/holidays/{holiday}', [\App\Http\Controllers\Classroom\HolidayController::class, 'destroy'])->name('holidays.destroy');
    });
});

// Public Link redirection
Route::get('/link/{token}', [\App\Http\Controllers\Classroom\LinkClaimController::class, 'show'])->name('link.show');

Route::middleware(['auth', 'can:manage_screen_builder'])
    ->prefix('admin/screen-builder')
    ->group(function () {
        Route::get('/preview/{template}', TemplatePreviewController::class)
            ->name('builder.preview');
    });
