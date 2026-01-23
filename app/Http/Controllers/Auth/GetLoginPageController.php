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
        $overrideParts = $renderer->renderPublishedPartsByKey('auth.login', [
            'user' => auth()->user(),
            'locale' => app()->getLocale(),
            'page' => [],
        ]);

        if ($overrideParts) {
            return response()->view('builder.screen', [
                'html' => $overrideParts['html'],
                'css' => $overrideParts['css'],
                'js' => $overrideParts['js'],
            ]);
        }

        return Inertia::render('Auth/Login', []);
    }
}

