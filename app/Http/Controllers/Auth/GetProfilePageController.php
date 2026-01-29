<?php

namespace App\Http\Controllers\Auth;

use Inertia\Inertia;
use Inertia\Response;

class GetProfilePageController
{
    /**
     * Display a basic profile page.
     */
    public function __invoke(): Response
    {
        $user = auth()->user();
        
        if (!$user) {
            return redirect()->route('auth.code');
        }

        $classrooms = $user->classrooms()
            ->with(['city', 'school'])
            ->get()
            ->map(function ($classroom) {
                return [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'grade_level' => $classroom->grade_level,
                    'grade_number' => $classroom->grade_number,
                    'city_name' => $classroom->city?->name,
                    'school_name' => $classroom->school?->name,
                    'role' => $classroom->pivot->role,
                ];
            });

        return Inertia::render('Profile', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
            ],
            'classrooms' => $classrooms,
        ]);
    }
}
