<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Rominas\SpecialAwards\Entry\Controllers\SpecialEntriesController;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;

// Admin review of the entries members propose in an edition's special categories, and entry of an
// appointed category's result. The `specialAwards` permission is checked against the SpecialEntry class;
// the controller enforces edition ownership.
Route::get('/{edition}/special-entries', [SpecialEntriesController::class, 'index'])
    ->name('special-entries.index')
    ->middleware('can:viewAny,' . SpecialEntry::class);

Route::get('/{edition}/special-entries/{specialEntry}', [SpecialEntriesController::class, 'show'])
    ->name('special-entries.show')
    ->middleware('can:view,specialEntry');

Route::patch('/{edition}/special-entries/{specialEntry}', [SpecialEntriesController::class, 'update'])
    ->name('special-entries.update')
    ->middleware('can:update,specialEntry');

Route::post('/{edition}/special-entries/{specialEntry}/approve', [SpecialEntriesController::class, 'approve'])
    ->name('special-entries.approve')
    ->middleware('can:update,specialEntry');

Route::post('/{edition}/special-entries/{specialEntry}/reject', [SpecialEntriesController::class, 'reject'])
    ->name('special-entries.reject')
    ->middleware('can:update,specialEntry');

Route::post('/{edition}/special-entries/{specialEntry}/merge', [SpecialEntriesController::class, 'merge'])
    ->name('special-entries.merge')
    ->middleware('can:update,specialEntry');

Route::put('/{edition}/categories/{category}/appointed-result', [SpecialEntriesController::class, 'setAppointedResult'])
    ->name('appointed-result.set')
    ->middleware('can:create,' . SpecialEntry::class);

Route::delete('/{edition}/categories/{category}/appointed-result', [SpecialEntriesController::class, 'clearAppointedResult'])
    ->name('appointed-result.clear')
    ->middleware('can:create,' . SpecialEntry::class);
