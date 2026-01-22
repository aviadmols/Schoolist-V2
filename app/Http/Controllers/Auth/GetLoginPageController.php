<?php

namespace App\Http\Controllers\Auth;

use App\Services\Builder\TemplateRenderer;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class GetLoginPageController
{
    /**
     * Display the login page.
     */
    public function __invoke(TemplateRenderer $renderer): Response
    {
        $overrideHtml = $renderer->renderPublishedByKey('auth.login', [
            'user' => auth()->user(),
            'locale' => app()->getLocale(),
            'page' => [],
        ]);

        if ($overrideHtml) {
            return response()->view('builder.screen', [
                'html' => $overrideHtml,
            ]);
        }

        return Inertia::render('Auth/Login', []);
    }
}

