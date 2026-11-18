<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Rominas\Academy\Shortlist\Controllers\ShortlistController;
use Rominas\Academy\Shortlist\Model\ShortlistEntry;

// Admin nominee shortlist, nested under an edition. Generation is on-demand (no auto listener);
// the `shortlists` permission is checked against the ShortlistEntry class (no instance is bound).
Route::get('/{edition}/shortlist', [ShortlistController::class, 'index'])
    ->name('shortlist.index')
    ->middleware('can:viewAny,' . ShortlistEntry::class);

// The edition's shortlists as an XLSX workbook, one sheet per ranked category.
Route::get('/{edition}/shortlist/export', [ShortlistController::class, 'export'])
    ->name('shortlist.export')
    ->middleware('can:viewAny,' . ShortlistEntry::class);

Route::post('/{edition}/shortlist', [ShortlistController::class, 'generateEdition'])
    ->name('shortlist.generate')
    ->middleware('can:create,' . ShortlistEntry::class);

Route::post('/{edition}/categories/{category}/shortlist', [ShortlistController::class, 'generateCategory'])
    ->name('shortlist.generate-category')
    ->middleware('can:create,' . ShortlistEntry::class);

// The ranked candidate pool for the manual review UI, and the admin-curated final shortlist it submits.
Route::get('/{edition}/categories/{category}/shortlist/candidates', [ShortlistController::class, 'candidates'])
    ->name('shortlist.candidates')
    ->middleware('can:viewAny,' . ShortlistEntry::class);

Route::put('/{edition}/categories/{category}/shortlist', [ShortlistController::class, 'adjust'])
    ->name('shortlist.adjust')
    ->middleware('can:update,' . ShortlistEntry::class);
