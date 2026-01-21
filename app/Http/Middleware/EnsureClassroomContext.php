<?php

namespace App\Http\Middleware;

use App\Services\Classroom\ClassroomContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClassroomContext
{
    /** @var ClassroomContextService */
    private ClassroomContextService $contextService;

    public function __construct(ClassroomContextService $contextService)
    {
        $this->contextService = $contextService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $classroom = $this->contextService->getCurrentClassroom();

        if (!$classroom) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Classroom context required.'], 403);
            }

            return redirect()->route('landing')->with('error', 'Please select a classroom first.');
        }

        // Share with Inertia or append to request
        $request->attributes->set('current_classroom', $classroom);

        return $next($request);
    }
}
