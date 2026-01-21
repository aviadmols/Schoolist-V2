<?php

namespace App\Http\Controllers\Classroom;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ClassLink;
use App\Services\Audit\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class LinkClaimController extends Controller
{
    /** @var AuditService */
    private AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Handle the initial link visit.
     */
    public function show(string $token): RedirectResponse
    {
        Session::put('claim_token', $token);

        if (!auth()->check()) {
            return redirect()->route('login')->with('info', 'Please login to claim this link.');
        }

        return redirect()->route('classroom.claim.view');
    }

    /**
     * Show the claim flow page.
     */
    public function view(): Response
    {
        $token = Session::get('claim_token');
        if (!$token) {
            return redirect()->route('landing');
        }

        // Get classrooms user can claim to (owner or admin)
        $classrooms = auth()->user()->classrooms()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->get();

        return Inertia::render('Classroom/LinkClaim', [
            'token' => $token,
            'classrooms' => $classrooms,
        ]);
    }

    /**
     * Claim the link to a specific classroom.
     */
    public function claim(Request $request): RedirectResponse
    {
        $request->validate([
            'classroom_id' => ['required', 'exists:classrooms,id'],
        ]);

        $token = Session::get('claim_token');
        if (!$token) {
            return redirect()->route('landing');
        }

        $classroom = Classroom::findOrFail($request->classroom_id);

        // Authorization check
        $membership = $classroom->users()->where('user_id', auth()->id())->first();
        if (!$membership || !in_array($membership->pivot->role, ['owner', 'admin'])) {
            abort(403);
        }

        // Logic for "claiming" a link. 
        // Assuming the token represents some predefined resource or just creating a new generic link.
        // For this task, we'll just create a ClassLink as a placeholder for "claiming".
        $link = ClassLink::create([
            'classroom_id' => $classroom->id,
            'title' => 'Claimed Link (' . substr($token, 0, 4) . ')',
            'url' => 'https://example.com/claimed/' . $token,
        ]);

        $this->auditService->log('link.claimed', $link, null, ['token' => $token], $classroom->id);

        Session::forget('claim_token');

        return redirect()->route('landing')->with('success', 'Link claimed successfully.');
    }
}
