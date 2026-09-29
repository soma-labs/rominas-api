<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Rominas\Results\Controllers\ResultsEditionsController;
use Rominas\Results\Model\ResultSnapshot;

// The editions whose results a custodian can open (voting closed onward), so the results page can offer a
// picker without needing the `editions` permission.
Route::get('/editions', [ResultsEditionsController::class, 'index'])
    ->name('editions.index')
    ->middleware('can:viewAny,' . ResultSnapshot::class);
