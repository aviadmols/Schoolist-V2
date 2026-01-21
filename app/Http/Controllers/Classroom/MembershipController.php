<?php

namespace App\Http\Controllers\Classroom;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class MembershipController extends Controller
{
    /** @var AuditService */
    private AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Update a user's role in the classroom.
     */
    public function updateRole(Request $request, Classroom $classroom, User $user): RedirectResponse
    {
        $request->validate([
            'role' => ['required', 'in:admin,member'],
        ]);

        // Authorization: Only owner or admin can manage roles
        $actorMembership = $classroom->users()->where('user_id', auth()->id())->first();
        if (!$actorMembership || !in_array($actorMembership->pivot->role, ['owner', 'admin'])) {
            abort(403);
        }

        // Only owner can promote to admin
        if ($request->role === 'admin' && $actorMembership->pivot->role !== 'owner') {
            abort(403);
        }

        $targetMembership = $classroom->users()->where('user_id', $user->id)->first();
        if (!$targetMembership || $targetMembership->pivot->role === 'owner') {
            abort(403, 'Cannot change owner role.');
        }

        $oldRole = $targetMembership->pivot->role;
        $classroom->users()->updateExistingPivot($user->id, ['role' => $request->role]);

        $this->auditService->log(
            'membership.role_updated',
            $classroom,
            ['user_id' => $user->id, 'role' => $oldRole],
            ['user_id' => $user->id, 'role' => $request->role],
            $classroom->id
        );

        return back()->with('success', 'User role updated.');
    }

    /**
     * Remove a user from the classroom.
     */
    public function remove(Classroom $classroom, User $user): RedirectResponse
    {
        // Authorization
        $actorMembership = $classroom->users()->where('user_id', auth()->id())->first();
        if (!$actorMembership || !in_array($actorMembership->pivot->role, ['owner', 'admin'])) {
            abort(403);
        }

        $targetMembership = $classroom->users()->where('user_id', $user->id)->first();
        if (!$targetMembership || $targetMembership->pivot->role === 'owner') {
            abort(403, 'Cannot remove owner.');
        }

        $classroom->users()->detach($user->id);

        $this->auditService->log(
            'membership.removed',
            $classroom,
            ['user_id' => $user->id],
            null,
            $classroom->id
        );

        return back()->with('success', 'User removed from classroom.');
    }
}
