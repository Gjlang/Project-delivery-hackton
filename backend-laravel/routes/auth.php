<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    // The route NAME "login" matters even though there's no password form
    // behind it anymore -- Laravel's default unauthenticated-redirect
    // (Authenticate middleware) targets route('login').
    Route::get('login', [GoogleAuthController::class, 'promptLogin'])->name('login');

    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
