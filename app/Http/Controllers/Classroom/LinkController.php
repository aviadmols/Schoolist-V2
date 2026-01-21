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

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $link = ClassLink::create(array_merge($data, [
            'classroom_id' => $classroom->id,
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
}
