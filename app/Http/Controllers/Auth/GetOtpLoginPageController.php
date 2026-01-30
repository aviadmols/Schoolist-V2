<?php

namespace App\Http\Controllers\Auth;

use Inertia\Inertia;
use Inertia\Response;

class GetOtpLoginPageController
{
    /**
     * Display the OTP login page.
     */
    public function __invoke(): Response
    {
        $redirectAfterLogin = session('redirect_after_login');
        
        return Inertia::render('Auth/OtpLogin', [
            'redirect_after_login' => $redirectAfterLogin,
        ]);
    }
}
