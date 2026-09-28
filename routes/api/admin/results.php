<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Rominas\Results\Controllers\ResultsController;
use Rominas\Results\Model\ResultSnapshot;

// Custodian-gated final results, nested under an edition. The `results` permission is checked against
// the ResultSnapshot class (the route binds an Edition, not a snapshot row); the read enforces Scoring's
// "voting closed" gate, and once results are published it serves the frozen snapshot.
Route::get('/{edition}/results', [ResultsController::class, 'show'])
    ->name('results.show')
    ->middleware('can:viewAny,' . ResultSnapshot::class);

Route::get('/{edition}/results/export', [ResultsController::class, 'export'])
    ->name('results.export')
    ->middleware('can:viewAny,' . ResultSnapshot::class);
