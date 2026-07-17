<?php

use Illuminate\Support\Facades\Route;

/**
 * The real app lives at the Filament panel, /admin (see CLAUDE.md), and
 * Jetstream/Fortify still own /login, /register, and password-reset
 * (traditional Blade auth views, restyled to match static_prototype). '/'
 * itself is just a marketing landing page explaining what the app is and
 * linking to /admin/login — jumping straight to a bare login form with no
 * context isn't a good first impression for a guest.
 */
Route::get('/', fn () => view('landing'));

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', fn () => redirect('/admin'))->name('dashboard');
});
