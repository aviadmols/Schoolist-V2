<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClassroomAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        if (!$user) {
            return redirect()->route('auth.code')->with('redirect_after_login', $request->fullUrl());
        }

        $classroom = $request->route('classroom');
        
        if (!$classroom) {
            abort(404);
        }

        // Site admins can access any classroom
        if ($user->role === 'site_admin') {
            return $next($request);
        }

        // Eager load users relationship to avoid N+1 query
        if (!$classroom->relationLoaded('users')) {
            $classroom->load('users');
        }

        // Check if user belongs to this classroom using loaded relationship
        $belongsToClassroom = $classroom->users->contains('id', $user->id);

        if (!$belongsToClassroom) {
            return redirect()->route('profile.show')->with('error', 'אין לך גישה לכיתה זו.');
        }

        return $next($request);
    }
}
