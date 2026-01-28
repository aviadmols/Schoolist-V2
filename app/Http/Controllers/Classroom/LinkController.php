<?php

namespace App\Http\Controllers\Classroom;

use App\Http\Controllers\Controller;
use App\Models\ClassLink;
use App\Models\Classroom;
use App\Services\Audit\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class LinkController extends Controller
{
    /** @var AuditService */
    private AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Display a listing of useful links.
     */
    public function index(Request $request): Response
    {
        $classroom = $request->attributes->get('current_classroom');

        return Inertia::render('Links', [
            'links' => ClassLink::where('classroom_id', $classroom->id)
                ->orderBy('sort_order', 'asc')
                ->get(),
        ]);
    }

    /**
     * Store a newly created link.
     */
    public function store(Request $request): RedirectResponse
    {
        $classroom = $request->attributes->get('current_classroom');
        $user = auth()->user();

        // Check publishing permissions
        if (!$this->canPublish($classroom, $user)) {
            abort(403, 'You do not have permission to publish links.');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
            'file_path' => ['nullable', 'string'],
            'category' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $link = ClassLink::create(array_merge($data, [
            'classroom_id' => $classroom->id,
            'created_by_user_id' => $user->id,
        ]));

        $this->auditService->log('link.created', $link, null, $link->toArray(), $classroom->id);

        return back()->with('success', 'Link added.');
    }

    /**
     * Update the specified link.
     */
    public function update(Request $request, ClassLink $link): RedirectResponse
    {
        $classroom = $request->attributes->get('current_classroom');

        if ($link->classroom_id !== $classroom->id) {
            abort(403);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $oldValues = $link->toArray();
        $link->update($data);

        $this->auditService->log('link.updated', $link, $oldValues, $link->toArray(), $classroom->id);

        return back()->with('success', 'Link updated.');
    }

    /**
     * Remove the specified link.
     */
    public function destroy(Request $request, ClassLink $link): RedirectResponse
    {
        $classroom = $request->attributes->get('current_classroom');

        if ($link->classroom_id !== $classroom->id) {
            abort(403);
        }

        $this->auditService->log('link.deleted', $link, $link->toArray(), null, $classroom->id);
        $link->delete();

        return back()->with('success', 'Link removed.');
    }

    /**
     * Check if user can publish to classroom.
     *
     * @param Classroom $classroom
     * @param \App\Models\User|null $user
     * @return bool
     */
    private function canPublish(Classroom $classroom, ?\App\Models\User $user): bool
    {
        if (!$user) {
            return false;
        }

        // Site admin can always publish
        if ($user->role === 'site_admin') {
            return true;
        }

        // If member posting is allowed, anyone can publish
        if ($classroom->allow_member_posting ?? true) {
            return true;
        }

        // Otherwise, only classroom admins can publish
        $membership = $classroom->users()->where('user_id', $user->id)->first();
        $isAdmin = $membership && in_array($membership->pivot->role, ['owner', 'admin']);
        $isClassroomAdmin = in_array($user->id, $classroom->classroom_admins ?? []);

        return $isAdmin || $isClassroomAdmin;
    }
}
