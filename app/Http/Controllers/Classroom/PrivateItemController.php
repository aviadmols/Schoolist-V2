<?php

namespace App\Http\Controllers\Classroom;

use App\Http\Controllers\Controller;
use App\Models\PrivateItem;
use App\Services\Announcements\AnnouncementWindowService;
use App\Services\Audit\AuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class PrivateItemController extends Controller
{
    /** @var AnnouncementWindowService */
    private AnnouncementWindowService $windowService;

    /** @var AuditService */
    private AuditService $auditService;

    public function __construct(AnnouncementWindowService $windowService, AuditService $auditService)
    {
        $this->windowService = $windowService;
        $this->auditService = $auditService;
    }

    /**
     * Display a listing of private items for the user.
     */
    public function index(Request $request): Response
    {
        $classroom = $request->attributes->get('current_classroom');
        $now = Carbon::now($classroom->timezone);

        $items = PrivateItem::where('user_id', auth()->id())
            ->where('classroom_id', $classroom->id)
            ->get()
            ->filter(function (PrivateItem $item) use ($classroom, $now) {
                // If no date is set, show always. If set, apply 16:00 window.
                if (!$item->occurs_on_date) {
                    return true;
                }

                $window = $this->windowService->getVisibilityWindow(
                    $item->occurs_on_date->toDateString(),
                    null,
                    $classroom->timezone
                );

                return $now->between($window['from'], $window['until']);
            })
            ->values();

        return Inertia::render('PrivateItems', [
            'items' => $items,
        ]);
    }

    /**
     * Store a newly created private item.
     */
    public function store(Request $request): RedirectResponse
    {
        $classroom = $request->attributes->get('current_classroom');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'occurs_on_date' => ['nullable', 'date'],
        ]);

        $item = PrivateItem::create(array_merge($data, [
            'user_id' => auth()->id(),
            'classroom_id' => $classroom->id,
        ]));

        $this->auditService->log('private_item.created', $item, null, $item->toArray(), $classroom->id);

        return back()->with('success', 'Private item added.');
    }

    /**
     * Update the specified private item.
     */
    public function update(Request $request, PrivateItem $privateItem): RedirectResponse
    {
        // Policy check via middleware or manual
        if ($privateItem->user_id !== auth()->id()) {
            abort(403);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'occurs_on_date' => ['nullable', 'date'],
        ]);

        $oldValues = $privateItem->toArray();
        $privateItem->update($data);

        $this->auditService->log('private_item.updated', $privateItem, $oldValues, $privateItem->toArray(), $privateItem->classroom_id);

        return back()->with('success', 'Private item updated.');
    }

    /**
     * Remove the specified private item.
     */
    public function destroy(PrivateItem $privateItem): RedirectResponse
    {
        if ($privateItem->user_id !== auth()->id()) {
            abort(403);
        }

        $this->auditService->log('private_item.deleted', $privateItem, $privateItem->toArray(), null, $privateItem->classroom_id);
        $privateItem->delete();

        return back()->with('success', 'Private item removed.');
    }
}
