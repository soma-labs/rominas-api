<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Rominas\SpecialAwards\Controllers\MemberSpecialAwardsController;

// Special awards for the authenticated academy member (guard `member`): per category, propose one entry
// or vote for one approved entry.
Route::get('/special-awards', [MemberSpecialAwardsController::class, 'show'])->name('special-awards.show');

Route::post('/special-awards/categories/{category}/proposal', [MemberSpecialAwardsController::class, 'propose'])
    ->name('special-awards.proposal.create');

Route::delete('/special-awards/categories/{category}/proposal', [MemberSpecialAwardsController::class, 'withdrawProposal'])
    ->name('special-awards.proposal.withdraw');

Route::put('/special-awards/categories/{category}/vote', [MemberSpecialAwardsController::class, 'vote'])
    ->name('special-awards.vote.cast');

Route::delete('/special-awards/categories/{category}/vote', [MemberSpecialAwardsController::class, 'withdrawVote'])
    ->name('special-awards.vote.withdraw');

// Approved entries while typing a proposal's title; one request per pause in typing, hence the high limit.
Route::get('/special-awards/categories/{category}/suggestions', [MemberSpecialAwardsController::class, 'suggestions'])
    ->middleware('throttle:120,1')
    ->name('special-awards.suggestions');
