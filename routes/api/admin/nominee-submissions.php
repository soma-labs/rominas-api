<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Rominas\Catalog\NomineeSubmission\Controllers\NomineeSubmissionsController;
use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;

// Admin reconciliation of free-text nominee submissions, nested under an edition. The `nomineeSubmissions`
// permission is checked against the NomineeSubmission class; the controller enforces edition ownership.
Route::get('/{edition}/nominee-submissions', [NomineeSubmissionsController::class, 'index'])
    ->name('nominee-submissions.index')
    ->middleware('can:viewAny,' . NomineeSubmission::class);

Route::get('/{edition}/nominee-submissions/{nomineeSubmission}', [NomineeSubmissionsController::class, 'show'])
    ->name('nominee-submissions.show')
    ->middleware('can:view,nomineeSubmission');

Route::post('/{edition}/nominee-submissions/{nomineeSubmission}/link', [NomineeSubmissionsController::class, 'link'])
    ->name('nominee-submissions.link')
    ->middleware('can:update,nomineeSubmission');

Route::post('/{edition}/nominee-submissions/{nomineeSubmission}/create', [NomineeSubmissionsController::class, 'create'])
    ->name('nominee-submissions.create')
    ->middleware('can:update,nomineeSubmission');

Route::post('/{edition}/nominee-submissions/{nomineeSubmission}/reject', [NomineeSubmissionsController::class, 'reject'])
    ->name('nominee-submissions.reject')
    ->middleware('can:update,nomineeSubmission');
