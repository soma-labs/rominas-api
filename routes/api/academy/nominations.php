<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Rominas\Academy\Nomination\Controllers\NominationsController;

// Ranked nominations for the authenticated academy member (guard `member`).
Route::get('/nominations', [NominationsController::class, 'show'])->name('nominations.show');

Route::put('/nominations/categories/{category}', [NominationsController::class, 'saveCategory'])
    ->name('nominations.categories.save');

// Catalog suggestions while typing a nominee name; one request per pause in typing, hence the high limit.
Route::get('/nominations/categories/{category}/suggestions', [NominationsController::class, 'suggestions'])
    ->middleware('throttle:120,1')
    ->name('nominations.categories.suggestions');

Route::post('/nominations/submit', [NominationsController::class, 'submit'])->name('nominations.submit');
