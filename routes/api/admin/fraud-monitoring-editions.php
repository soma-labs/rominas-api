<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Rominas\FraudMonitoring\Controllers\FraudMonitoringEditionsController;
use Rominas\FraudMonitoring\Model\InvalidationBatch;

// The editions a fraud monitor can open (public voting started onward), with per-edition ballot and alert
// counts, so the fraud page can offer a picker without needing the `editions` permission.
Route::get('/editions', [FraudMonitoringEditionsController::class, 'index'])
    ->name('editions.index')
    ->middleware('can:viewAny,' . InvalidationBatch::class);
