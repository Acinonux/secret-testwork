<?php

use App\Http\Controllers\SecretController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::pattern('secret', '[A-Za-z0-9]{32}');

Route::get('/', fn () => Inertia::render('Welcome'))->name('home');

Route::prefix('api/secrets')->name('secrets.')->group(function () {
    Route::post('/', [SecretController::class, 'store'])
        ->middleware('throttle:secrets-store')->name('store');
    Route::post('{secret}/reveal', [SecretController::class, 'reveal'])
        ->middleware('throttle:secrets-reveal')->name('reveal');
});
