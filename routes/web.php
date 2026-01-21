<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GetLoginPageController;
use App\Http\Controllers\Public\GetLandingPageController;

Route::get('/', GetLandingPageController::class)->name('landing');

Route::get('/login', GetLoginPageController::class)->name('login');

Route::prefix('auth')->group(function () {
    Route::post('/otp/request', \App\Http\Controllers\Auth\RequestOtpController::class)
        ->middleware('throttle:otp')
        ->name('auth.otp.request');

    Route::post('/otp/verify', \App\Http\Controllers\Auth\VerifyOtpController::class)
        ->name('auth.otp.verify');    Route::post('/register', \App\Http\Controllers\Auth\RegisterController::class)
        ->name('auth.register');
});
