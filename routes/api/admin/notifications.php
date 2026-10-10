<?php

declare(strict_types=1);

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Route;
use Rominas\Notifications\Controllers\NotificationsController;

// The signed-in admin's own notifications. Marking as read is listed in config('audit.ignore'): it is
// not a sensitive action and would fill the audit trail.
Route::get('/', [NotificationsController::class, 'index'])
    ->name('index')
    ->middleware('can:viewAny,' . DatabaseNotification::class);

Route::get('/unread-count', [NotificationsController::class, 'unreadCount'])
    ->name('unread-count')
    ->middleware('can:viewAny,' . DatabaseNotification::class);

Route::post('/read-all', [NotificationsController::class, 'markAllAsRead'])
    ->name('read-all')
    ->middleware('can:viewAny,' . DatabaseNotification::class);

Route::post('/{notification}/read', [NotificationsController::class, 'markAsRead'])
    ->name('read')
    ->middleware('can:update,notification');
