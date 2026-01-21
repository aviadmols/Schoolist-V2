<?php

namespace App\Http\Controllers\Public;

use Inertia\Inertia;
use Inertia\Response;

class GetLandingPageController
{
    /**
     * Display the public landing page.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Landing', []);
    }
}

