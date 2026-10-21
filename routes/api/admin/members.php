<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Rominas\Academy\Member\Controllers\MembersController;
use Rominas\Academy\Member\Model\Member;

Route::get('/', [MembersController::class, 'index'])
    ->name('index')
    ->middleware('can:viewAny,' . Member::class);

Route::post('/', [MembersController::class, 'create'])
    ->name('create')
    ->middleware('can:create,' . Member::class);

// Bulk: (re)send invitations to every member still awaiting one, on demand.
Route::post('/invitations', [MembersController::class, 'inviteAll'])
    ->name('invitations')
    ->middleware('can:create,' . Member::class);

// Bulk: invitations in a chosen template to the members the admin picked (each still awaiting one).
Route::post('/invitations/send', [MembersController::class, 'invitePicked'])
    ->name('invitations.send')
    ->middleware('can:create,' . Member::class);

// The invitation templates an admin can choose from.
Route::get('/invitation-templates', [MembersController::class, 'invitationTemplates'])
    ->name('invitation-templates')
    ->middleware('can:create,' . Member::class);

// Bulk: one member awaiting invitation per row of an uploaded CSV / XLSX list; sends nothing.
Route::post('/import', [MembersController::class, 'import'])
    ->name('import')
    ->middleware('can:create,' . Member::class);

Route::get('/export', [MembersController::class, 'export'])
    ->name('export')
    ->middleware('can:viewAny,' . Member::class);

Route::post('/nomination-follow-ups/send', [MembersController::class, 'sendNominationFollowUps'])
    ->name('nomination-follow-ups.send')
    ->middleware('can:create,' . Member::class);

Route::get('/{member}', [MembersController::class, 'show'])
    ->name('view')
    ->middleware('can:view,member');

Route::patch('/{member}', [MembersController::class, 'update'])
    ->name('update')
    ->middleware('can:update,member');

Route::post('/{member}/invite', [MembersController::class, 'invite'])
    ->name('invite')
    ->middleware('can:update,member');

Route::delete('/{member}', [MembersController::class, 'delete'])
    ->name('delete')
    ->middleware('can:delete,member');
