<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Rominas\Auth\Controllers\AuthController;
use Rominas\Menu\Controllers\MenuController;
use Rominas\Users\Controllers\UsersController;

// Admin / management authentication (username + password → Sanctum token). Audited (login/logout).
Route::post('/authenticate', [AuthController::class, 'authenticate'])
    ->middleware('audit')
    ->name('api.authenticate');

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware(['auth:sanctum', 'audit'])
    ->name('api.logout');

// ---------------------------------------------------------------------------
// Academy member-facing API (passwordless magic-link auth on the `member` guard).
// ---------------------------------------------------------------------------
// Audited: the magic-link request/verify and member logout (opt-in by route name in config/audit.php).
Route::middleware('audit')->prefix('/academy')->name('api.academy.')
    ->group(__DIR__ . '/api/academy/auth.php');

Route::middleware('auth:member')->prefix('/academy')->name('api.academy.')
    ->group(__DIR__ . '/api/academy/nominations.php');

// Audited: member proposal withdrawal (opt-in by route name).
Route::middleware(['auth:member', 'audit'])->prefix('/academy')->name('api.academy.')
    ->group(__DIR__ . '/api/academy/proposals.php');

// ---------------------------------------------------------------------------
// Public voting API (accountless — a one-time link token authorizes the voter, no auth middleware).
// ---------------------------------------------------------------------------
Route::prefix('/voting')->name('api.voting.')->group(__DIR__ . '/api/voting/public.php');

// ---------------------------------------------------------------------------
// Public results API (unauthenticated — a published edition's frozen results snapshot).
// ---------------------------------------------------------------------------
Route::prefix('/results')->name('api.results.')->group(__DIR__ . '/api/results.php');

// ---------------------------------------------------------------------------
// Admin / management API (Sanctum-guarded, per-concern files under routes/api/admin/).
// ---------------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'audit'])->prefix('/admin')->name('api.admin.')->group(function (): void {
    // The authenticated admin's own account, and their permission-filtered sidebar menu.
    Route::get('/me', [UsersController::class, 'me'])->name('me');
    Route::get('/menu', [MenuController::class, 'menu'])->name('menu');

    Route::prefix('/permissions')->name('permissions.')->group(__DIR__ . '/api/admin/permissions.php');
    Route::prefix('/roles')->name('roles.')->group(__DIR__ . '/api/admin/roles.php');
    Route::prefix('/users')->name('users.')->group(__DIR__ . '/api/admin/users.php');
    Route::prefix('/taxonomies')->name('taxonomies.')->group(__DIR__ . '/api/admin/taxonomies.php');
    Route::prefix('/taxonomy-terms')->name('taxonomy-terms.')->group(__DIR__ . '/api/admin/taxonomy-terms.php');

    Route::prefix('/editions')->name('editions.')->group(__DIR__ . '/api/admin/editions.php');
    Route::prefix('/editions')->name('editions.')->group(__DIR__ . '/api/admin/shortlist.php');
    Route::prefix('/editions')->name('editions.')->group(__DIR__ . '/api/admin/results.php');
    Route::prefix('/editions')->name('editions.')->group(__DIR__ . '/api/admin/fraud-monitoring.php');
    Route::prefix('/editions')->name('editions.')->group(__DIR__ . '/api/admin/nominee-submissions.php');
    Route::prefix('/categories')->name('categories.')->group(__DIR__ . '/api/admin/categories.php');
    Route::prefix('/members')->name('members.')->group(__DIR__ . '/api/admin/members.php');
    Route::prefix('/member-proposals')->name('member-proposals.')->group(__DIR__ . '/api/admin/member-proposals.php');

    Route::prefix('/artists')->name('artists.')->group(__DIR__ . '/api/admin/artists.php');
    Route::prefix('/bands')->name('bands.')->group(__DIR__ . '/api/admin/bands.php');
    Route::prefix('/venues')->name('venues.')->group(__DIR__ . '/api/admin/venues.php');
    Route::prefix('/songs')->name('songs.')->group(__DIR__ . '/api/admin/songs.php');
    Route::prefix('/albums')->name('albums.')->group(__DIR__ . '/api/admin/albums.php');

    Route::prefix('/audit-logs')->name('audit-logs.')->group(__DIR__ . '/api/admin/audit.php');

    Route::prefix('/reports')->name('reports.')->group(__DIR__ . '/api/admin/reporting.php');
});
