<?php

namespace App\Http\Controllers\Classroom;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementUserStatus;
use App\Models\Classroom;
use App\Services\Announcements\AnnouncementFeedService;
use App\Services\Audit\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class AnnouncementController extends Controller
{
    /** @var AnnouncementFeedService */
    private AnnouncementFeedService $feedService;

    /** @var AuditService */
    private AuditService $auditService;

    public function __construct(AnnouncementFeedService $feedService, AuditService $auditService)
    {
        $this->feedService = $feedService;
        $this->auditService = $auditService;
    }

    /**
     * Display the announcement feed.
     */
    public function index(Request $request): Response
    {
        $classroom = $request->attributes->get('current_classroom');

        return Inertia::render('Announcements', [
            'announcements' => $this->feedService->getActiveFeed($classroom),
        ]);
    }

    /**
     * Store a new announcement.
     */
    public function store(Request $request): RedirectResponse
    {
        $classroom = $request->attributes->get('current_classroom');

        $data = $request->validate([
            'type' => ['required', 'in:message,homework,event'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'occurs_on_date' => ['nullable', 'date'],
            'day_of_week' => ['nullable', 'integer', 'between:0,6'],
        ]);

        $announcement = Announcement::create(array_merge($data, [
            'classroom_id' => $classroom->id,
            'user_id' => auth()->id(),
        ]));

        $this->auditService->log('announcement.created', $announcement, null, $announcement->toArray(), $classroom->id);

        return back()->with('success', 'Announcement created.');
    }

    /**
     * Toggle the done state for the current user.
     */
    public function toggleDone(Request $request, Announcement $announcement): RedirectResponse
    {
        $status = AnnouncementUserStatus::firstOrNew([
            'announcement_id' => $announcement->id,
            'user_id' => auth()->id(),
        ]);

        $status->done_at = $status->done_at ? null : now();
        $status->save();

        return back();
    }

    /**
     * Delete an announcement.
     */
    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        $classroom = $request->attributes->get('current_classroom');

        // Check ownership/admin
        $membership = $classroom->users()->where('user_id', auth()->id())->first();
        if (!$membership || !in_array($membership->pivot->role, ['owner', 'admin'])) {
            abort(403);
        }

        $this->auditService->log('announcement.deleted', $announcement, $announcement->toArray(), null, $classroom->id);
        $announcement->delete();

        return back()->with('success', 'Announcement deleted.');
    }
}
