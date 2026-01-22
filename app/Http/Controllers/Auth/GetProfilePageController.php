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
        return Inertia::render('Profile', [
            'user' => [
                'name' => auth()->user()?->name,
                'phone' => auth()->user()?->phone,
            ],
        ]);
    }
}
