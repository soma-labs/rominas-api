<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
use Rominas\FraudMonitoring\Enums\FraudAlertStatus;
use Rominas\FraudMonitoring\Model\FraudAlert;
use Rominas\FraudMonitoring\Model\InvalidationBatch;
use Rominas\Permissions\Model\Permission;
use Rominas\Roles\Model\Role;
use Rominas\Users\Model\User;
use Rominas\Voting\Model\Ballot;

use function Pest\Laravel\getJson;

/**
 * Authenticate as a user holding the `fraudMonitoring` permission via the given role. Uniquely-named so the
 * file runs in isolation.
 */
function fraudEditionsActingAs(string $role): User
{
    Role::query()->firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    Permission::query()->firstOrCreate(['name' => 'fraudMonitoring', 'guard_name' => 'web']);

    $roleModel = Role::query()->where('name', $role)->where('guard_name', 'web')->firstOrFail();
    $roleModel->givePermissionTo('fraudMonitoring');

    $user = User::factory()->create();
    $user->assignRole($role);

    Sanctum::actingAs($user);

    return $user;
}

it('lists only editions whose public voting has started, with ballot and alert counts', function (): void {
    fraudEditionsActingAs('fraud_monitor');

    Edition::factory()->status(EditionStatus::NominationsClosed)->create();
    $open = Edition::factory()->status(EditionStatus::VotingOpen)->create();
    $published = Edition::factory()->status(EditionStatus::ResultsPublished)->create();

    $batch = InvalidationBatch::factory()->create(['edition_id' => $open->id]);
    Ballot::factory()->submitted()->count(3)->create(['edition_id' => $open->id]);
    Ballot::factory()->submitted()->create(['edition_id' => $open->id, 'invalidation_batch_id' => $batch->id]);
    Ballot::factory()->create(['edition_id' => $open->id]); // issued, not submitted: not counted
    FraudAlert::factory()->count(2)->create(['edition_id' => $open->id]);
    FraudAlert::factory()->create(['edition_id' => $open->id, 'status' => FraudAlertStatus::Solved]);

    $response = getJson('/api/admin/fraud-monitoring/editions')->assertStatus(200);

    $rows = collect($response->json('data'))->keyBy('id');

    expect($rows->keys()->sort()->values()->all())->toBe(collect([$open->id, $published->id])->sort()->values()->all())
        ->and($rows[$open->id]['submitted_ballots_count'])->toBe(4)
        ->and($rows[$open->id]['cancelled_ballots_count'])->toBe(1)
        ->and($rows[$open->id]['pending_alerts_count'])->toBe(2)
        ->and($rows[$open->id]['cancellation_allowed'])->toBeTrue()
        ->and($rows[$published->id]['cancellation_allowed'])->toBeFalse()
        ->and($rows[$published->id]['submitted_ballots_count'])->toBe(0);
});

it('lets fraud monitors and custodians open the picker, but forbids others', function (string $role, int $status): void {
    if ($role === 'admin') {
        $user = User::factory()->create();
        $user->assignRole(Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])->name);
        Sanctum::actingAs($user);
    } else {
        fraudEditionsActingAs($role);
    }

    getJson('/api/admin/fraud-monitoring/editions')->assertStatus($status);
})->with([
    ['fraud_monitor', 200],
    ['custodian', 200],
    ['admin', 403],
]);

it('rejects unauthenticated access to the picker', function (): void {
    getJson('/api/admin/fraud-monitoring/editions')->assertStatus(401);
});
