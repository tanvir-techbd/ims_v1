<?php

use Illuminate\Support\Facades\Route;

/**
 * This app has a single real UI surface — the Filament panel at /admin
 * (see CLAUDE.md). Jetstream/Fortify still own /login, /register, and
 * password-reset (traditional Blade auth views, restyled to match
 * static_prototype), but there's no separate marketing homepage or
 * Jetstream dashboard — both routes below just hand off to /admin, whose
 * own auth middleware redirects a guest to /admin/login.
 */
Route::get('/', fn () => redirect('/admin'));

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', fn () => redirect('/admin'))->name('dashboard');
});
