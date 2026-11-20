<?php

use App\Http\Controllers\SecretController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::pattern('secret', '[A-Za-z0-9]{32}');

Route::get('/', fn () => Inertia::render('Create', [
    'ttlOptions' => config('secrets.ttl_options'),
    'defaultTtl' => config('secrets.default_ttl'),
    'maxFileBytes' => config('secrets.max_file_bytes'),
]))->name('home');

// Только отдаёт страницу: секрет не читается и не удаляется (безопасно для превью ссылок).
Route::get('s/{secret}', fn (string $secret) => Inertia::render('Show', ['id' => $secret]))->name('secrets.show');

Route::prefix('api/secrets')->name('secrets.')->group(function () {
    Route::post('/', [SecretController::class, 'store'])
        ->middleware('throttle:secrets-store')->name('store');
    Route::post('{secret}/reveal', [SecretController::class, 'reveal'])
        ->middleware('throttle:secrets-reveal')->name('reveal');
});
