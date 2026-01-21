<?php

namespace App\Http\Controllers\Classroom;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ImportantContact;
use App\Services\Audit\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class ImportantContactController extends Controller
{
    /** @var AuditService */
    private AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Display the important contacts.
     */
    public function index(Request $request): Response
    {
        $classroom = $request->attributes->get('current_classroom');

        return Inertia::render('ImportantContacts', [
            'contacts' => ImportantContact::where('classroom_id', $classroom->id)
                ->orderBy('name', 'asc')
                ->get(),
        ]);
    }

    /**
     * Store a newly created important contact.
     */
    public function store(Request $request): RedirectResponse
    {
        $classroom = $request->attributes->get('current_classroom');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $contact = ImportantContact::create(array_merge($data, [
            'classroom_id' => $classroom->id,
        ]));

        $this->auditService->log('contacts.important_added', $contact, null, $contact->toArray(), $classroom->id);

        return back()->with('success', 'Important contact added.');
    }

    /**
     * Update the specified important contact.
     */
    public function update(Request $request, ImportantContact $contact): RedirectResponse
    {
        $classroom = $request->attributes->get('current_classroom');

        if ($contact->classroom_id !== $classroom->id) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $oldValues = $contact->toArray();
        $contact->update($data);

        $this->auditService->log('contacts.important_updated', $contact, $oldValues, $contact->toArray(), $classroom->id);

        return back()->with('success', 'Important contact updated.');
    }

    /**
     * Remove the specified important contact.
     */
    public function destroy(Request $request, ImportantContact $contact): RedirectResponse
    {
        $classroom = $request->attributes->get('current_classroom');

        if ($contact->classroom_id !== $classroom->id) {
            abort(403);
        }

        $this->auditService->log('contacts.important_deleted', $contact, $contact->toArray(), null, $classroom->id);
        $contact->delete();

        return back()->with('success', 'Important contact removed.');
    }
}
