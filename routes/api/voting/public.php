<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Rominas\Voting\Controllers\BallotController;
use Rominas\Voting\Controllers\VotingController;
use Rominas\Voting\Controllers\VotingStatusController;

// Public, accountless voting. No auth middleware — the one-time link token is the authorization.

// Where voting stands (upcoming / open / closed + window dates) — the landing page reads this first.
Route::get('/status', VotingStatusController::class)
    ->name('status')
    ->middleware('throttle:60,1');

Route::post('/request', [VotingController::class, 'request'])
    ->name('request')
    ->middleware('throttle:voting-request');

// Token-gated ballot: load the shortlist to rank (?token=), then cast it once.
Route::get('/ballot', [BallotController::class, 'show'])->name('ballot.show');
Route::post('/ballot', [BallotController::class, 'submit'])->name('ballot.submit');
