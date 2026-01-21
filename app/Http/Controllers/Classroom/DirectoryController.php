<?php

namespace App\Http\Controllers\Classroom;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\ChildContact;
use App\Models\Classroom;
use App\Services\Audit\AuditService;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class DirectoryController extends Controller
{
    /** @var AuditService */
    private AuditService $auditService;

    /** @var FileStorageService */
    private FileStorageService $fileStorageService;

    public function __construct(AuditService $auditService, FileStorageService $fileStorageService)
    {
        $this->auditService = $auditService;
        $this->fileStorageService = $fileStorageService;
    }

    /**
     * Display the classroom directory.
     */
    public function index(Request $request): Response
    {
        $classroom = $request->attributes->get('current_classroom');

        return Inertia::render('Directory', [
            'children' => Child::where('classroom_id', $classroom->id)
                ->with(['contacts', 'photo'])
                ->orderBy('name', 'asc')
                ->get(),
        ]);
    }

    /**
     * Store a newly created child and their contacts.
     */
    public function store(Request $request): RedirectResponse
    {
        $classroom = $request->attributes->get('current_classroom');

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'contacts' => ['required', 'array', 'min:1'],
            'contacts.*.name' => ['required', 'string', 'max:255'],
            'contacts.*.phone' => ['required', 'string', 'max:20'],
            'contacts.*.relation' => ['required', 'string', 'max:50'],
        ]);

        DB::transaction(function () use ($request, $classroom) {
            $child = Child::create([
                'classroom_id' => $classroom->id,
                'name' => $request->name,
            ]);

            if ($request->hasFile('photo')) {
                $fileRecord = $this->fileStorageService->storeUpload(
                    $classroom->id,
                    'child_photos',
                    $child->id,
                    $request->file('photo')
                );
                $child->update(['photo_file_id' => $fileRecord->id]);
            }

            foreach ($request->contacts as $contactData) {
                $child->contacts()->create($contactData);
            }

            $this->auditService->log('directory.child_added', $child, null, $child->load('contacts')->toArray(), $classroom->id);
        });

        return back()->with('success', 'Child added to directory.');
    }

    /**
     * Update the specified child and their contacts.
     */
    public function update(Request $request, Child $child): RedirectResponse
    {
        $classroom = $request->attributes->get('current_classroom');

        if ($child->classroom_id !== $classroom->id) {
            abort(403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'contacts' => ['required', 'array', 'min:1'],
            'contacts.*.name' => ['required', 'string', 'max:255'],
            'contacts.*.phone' => ['required', 'string', 'max:20'],
            'contacts.*.relation' => ['required', 'string', 'max:50'],
        ]);

        DB::transaction(function () use ($request, $child, $classroom) {
            $oldValues = $child->load('contacts')->toArray();

            $child->update(['name' => $request->name]);

            if ($request->hasFile('photo')) {
                // Delete old photo if exists
                if ($child->photo) {
                    $this->fileStorageService->deleteFile($child->photo);
                }

                $fileRecord = $this->fileStorageService->storeUpload(
                    $classroom->id,
                    'child_photos',
                    $child->id,
                    $request->file('photo')
                );
                $child->update(['photo_file_id' => $fileRecord->id]);
            }

            // Simple approach: sync contacts by deleting and re-creating
            $child->contacts()->delete();
            foreach ($request->contacts as $contactData) {
                $child->contacts()->create($contactData);
            }

            $this->auditService->log('directory.child_updated', $child, $oldValues, $child->load('contacts')->toArray(), $classroom->id);
        });

        return back()->with('success', 'Child updated.');
    }

    /**
     * Remove the specified child from the directory.
     */
    public function destroy(Request $request, Child $child): RedirectResponse
    {
        $classroom = $request->attributes->get('current_classroom');

        if ($child->classroom_id !== $classroom->id) {
            abort(403);
        }

        DB::transaction(function () use ($child, $classroom) {
            if ($child->photo) {
                $this->fileStorageService->deleteFile($child->photo);
            }

            $this->auditService->log('directory.child_deleted', $child, $child->load('contacts')->toArray(), null, $classroom->id);
            $child->delete();
        });

        return back()->with('success', 'Child removed from directory.');
    }
}
