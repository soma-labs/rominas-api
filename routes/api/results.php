<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Rominas\Results\Controllers\PublicResultsController;

// Public, unauthenticated results for an edition whose results are published — served from the frozen
// snapshot only (404 until the edition's results are published).
Route::get('/editions/{edition}', [PublicResultsController::class, 'show'])->name('editions.show');
