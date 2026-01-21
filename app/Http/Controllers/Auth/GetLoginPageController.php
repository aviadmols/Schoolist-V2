<?php

namespace App\Http\Controllers\Auth;

use Inertia\Inertia;
use Inertia\Response;

class GetLoginPageController
{
    /**
     * Display the login page.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Auth/Login', []);
    }
}

