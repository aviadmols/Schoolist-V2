<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GetLoginPageController;
use App\Http\Controllers\Public\GetLandingPageController;

Route::get('/', GetLandingPageController::class)->name('landing');

Route::get('/login', GetLoginPageController::class)->name('login');
Route::post('/login', \App\Http\Controllers\Auth\LoginController::class);

Route::prefix('auth')->group(function () {
    Route::post('/otp/request', \App\Http\Controllers\Auth\RequestOtpController::class)
        ->middleware('throttle:otp')
        ->name('auth.otp.request');

    Route::post('/otp/verify', \App\Http\Controllers\Auth\VerifyOtpController::class)
        ->name('auth.otp.verify');

    Route::post('/register', \App\Http\Controllers\Auth\RegisterController::class)
        ->name('auth.register');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', \App\Http\Controllers\Classroom\DashboardController::class)
        ->middleware('classroom.context')
        ->name('dashboard');

    Route::prefix('classrooms')->group(function () {
        Route::get('/', [\App\Http\Controllers\Classroom\ClassroomController::class, 'index'])->name('classroom.index');
        Route::get('/create', [\App\Http\Controllers\Classroom\ClassroomController::class, 'create'])->name('classroom.create');
        Route::post('/', [\App\Http\Controllers\Classroom\ClassroomController::class, 'store'])->name('classroom.store');
        Route::get('/join', [\App\Http\Controllers\Classroom\ClassroomController::class, 'showJoin'])->name('classroom.join.view');
        Route::post('/join', [\App\Http\Controllers\Classroom\ClassroomController::class, 'join'])->name('classroom.join');
        Route::post('/{classroom}/switch', [\App\Http\Controllers\Classroom\ClassroomController::class, 'switch'])->name('classroom.switch');
        
        // Link Claiming
        Route::get('/claim', [\App\Http\Controllers\Classroom\LinkClaimController::class, 'view'])->name('classroom.claim.view');
        Route::post('/claim', [\App\Http\Controllers\Classroom\LinkClaimController::class, 'claim'])->name('classroom.claim');

        // Membership management
        Route::post('/{classroom}/members/{user}/role', [\App\Http\Controllers\Classroom\MembershipController::class, 'updateRole'])->name('classroom.membership.update');
        Route::delete('/{classroom}/members/{user}', [\App\Http\Controllers\Classroom\MembershipController::class, 'remove'])->name('classroom.membership.remove');

        // File management
        Route::post('/{classroom}/files', [\App\Http\Controllers\Classroom\FileController::class, 'upload'])->name('classroom.files.upload');
        Route::get('/{classroom}/files/{file}/download', [\App\Http\Controllers\Classroom\FileController::class, 'download'])->name('classroom.files.download');
        Route::delete('/{classroom}/files/{file}', [\App\Http\Controllers\Classroom\FileController::class, 'destroy'])->name('classroom.files.destroy');

        // Useful Links
        Route::get('/links', [\App\Http\Controllers\Classroom\LinkController::class, 'index'])->name('classroom.links.index');
        Route::post('/links', [\App\Http\Controllers\Classroom\LinkController::class, 'store'])->name('classroom.links.store');
        Route::put('/links/{link}', [\App\Http\Controllers\Classroom\LinkController::class, 'update'])->name('classroom.links.update');
        Route::delete('/links/{link}', [\App\Http\Controllers\Classroom\LinkController::class, 'destroy'])->name('classroom.links.destroy');

        // WhatsApp Updates
        Route::get('/whatsapp', [\App\Http\Controllers\Classroom\LinkController::class, 'index'])->name('classroom.whatsapp.index');
        Route::post('/whatsapp', [\App\Http\Controllers\Classroom\LinkController::class, 'store'])->name('classroom.whatsapp.store');
        Route::put('/whatsapp/{link}', [\App\Http\Controllers\Classroom\LinkController::class, 'update'])->name('classroom.whatsapp.update');
        Route::delete('/whatsapp/{link}', [\App\Http\Controllers\Classroom\LinkController::class, 'destroy'])->name('classroom.whatsapp.destroy');

        // Directory
        Route::get('/directory', [\App\Http\Controllers\Classroom\DirectoryController::class, 'index'])->name('classroom.directory.index');
        Route::post('/directory', [\App\Http\Controllers\Classroom\DirectoryController::class, 'store'])->name('classroom.directory.store');
        Route::put('/directory/{child}', [\App\Http\Controllers\Classroom\DirectoryController::class, 'update'])->name('classroom.directory.update');
        Route::delete('/directory/{child}', [\App\Http\Controllers\Classroom\DirectoryController::class, 'destroy'])->name('classroom.directory.destroy');

        // Important Contacts
        Route::get('/important-contacts', [\App\Http\Controllers\Classroom\ImportantContactController::class, 'index'])->name('classroom.important_contacts.index');
        Route::post('/important-contacts', [\App\Http\Controllers\Classroom\ImportantContactController::class, 'store'])->name('classroom.important_contacts.store');
        Route::put('/important-contacts/{contact}', [\App\Http\Controllers\Classroom\ImportantContactController::class, 'update'])->name('classroom.important_contacts.update');
        Route::delete('/important-contacts/{contact}', [\App\Http\Controllers\Classroom\ImportantContactController::class, 'destroy'])->name('classroom.important_contacts.destroy');

        // Private Items
        Route::get('/private', [\App\Http\Controllers\Classroom\PrivateItemController::class, 'index'])->name('classroom.private.index');
        Route::post('/private', [\App\Http\Controllers\Classroom\PrivateItemController::class, 'store'])->name('classroom.private.store');
        Route::put('/private/{privateItem}', [\App\Http\Controllers\Classroom\PrivateItemController::class, 'update'])->name('classroom.private.update');
        Route::delete('/private/{privateItem}', [\App\Http\Controllers\Classroom\PrivateItemController::class, 'destroy'])->name('classroom.private.destroy');
    });

    // Scoped by classroom.context middleware
    Route::middleware('classroom.context')->group(function () {
        Route::get('/announcements', [\App\Http\Controllers\Classroom\AnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('/announcements', [\App\Http\Controllers\Classroom\AnnouncementController::class, 'store'])->name('announcements.store');
        Route::post('/announcements/{announcement}/done', [\App\Http\Controllers\Classroom\AnnouncementController::class, 'toggleDone'])->name('announcements.done');
        Route::delete('/announcements/{announcement}', [\App\Http\Controllers\Classroom\AnnouncementController::class, 'destroy'])->name('announcements.destroy');

        Route::get('/holidays', [\App\Http\Controllers\Classroom\HolidayController::class, 'index'])->name('holidays.index');
        Route::post('/holidays', [\App\Http\Controllers\Classroom\HolidayController::class, 'store'])->name('holidays.store');
        Route::put('/holidays/{holiday}', [\App\Http\Controllers\Classroom\HolidayController::class, 'update'])->name('holidays.update');
        Route::delete('/holidays/{holiday}', [\App\Http\Controllers\Classroom\HolidayController::class, 'destroy'])->name('holidays.destroy');
    });
});

// Public Link redirection
Route::get('/link/{token}', [\App\Http\Controllers\Classroom\LinkClaimController::class, 'show'])->name('link.show');
