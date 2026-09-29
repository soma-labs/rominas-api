<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
use Rominas\FraudMonitoring\Actions\DetectVotingFraudAction;
use Rominas\FraudMonitoring\Detectors\VelocityBurstDetector;
use Rominas\FraudMonitoring\Enums\FraudAlertStatus;
use Rominas\FraudMonitoring\Enums\FraudAlertType;
use Rominas\FraudMonitoring\Model\FraudAlert;
use Rominas\FraudMonitoring\Model\InvalidationBatch;
use Rominas\Permissions\Model\Permission;
use Rominas\Roles\Model\Role;
use Rominas\Users\Model\User;
use Rominas\Voting\Model\Ballot;
use Rominas\Voting\Model\BallotRanking;

use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;

/**
 * Isolate the detectors: default every threshold to 100 so a test only fires the detector it lowers.
 */
beforeEach(function (): void {
    config([
        'fraud.shared_ip.threshold' => 100,
        'fraud.velocity.window_minutes' => 10,
        'fraud.velocity.threshold' => 100,
        'fraud.identical_ranking.threshold' => 100,
    ]);
});

function alertsActingAs(string $role): User
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

function alertsSeedBallot(Edition $edition, ?string $ipHash = null, ?Carbon $submittedAt = null): Ballot
{
    return Ballot::factory()->submitted()->create([
        'edition_id' => $edition->id,
        'ip_hash' => $ipHash,
        'submitted_at' => $submittedAt ?? now(),
    ]);
}

/**
 * @param  list<int>  $nomineeIdsByRank
 */
function alertsSeedRankedBallot(Edition $edition, int $categoryId, array $nomineeIdsByRank): Ballot
{
    $ballot = alertsSeedBallot($edition, hash('sha256', fake()->unique()->ipv4()));

    foreach ($nomineeIdsByRank as $index => $nomineeId) {
        BallotRanking::factory()->create([
            'ballot_id' => $ballot->id,
            'category_id' => $categoryId,
            'rank' => $index + 1,
            'nominee_type' => NomineeType::Artist,
            'nominee_id' => $nomineeId,
        ]);
    }

    return $ballot;
}

it('records a shared-IP alert only when the cluster meets the threshold', function (): void {
    config(['fraud.shared_ip.threshold' => 3]);
    $edition = Edition::factory()->status(EditionStatus::VotingOpen)->create();

    $sharedIp = hash('sha256', '203.0.113.9');
    $clustered = collect(range(1, 3))->map(fn(): Ballot => alertsSeedBallot($edition, $sharedIp));
    alertsSeedBallot($edition, hash('sha256', '198.51.100.2')); // lone IP — below threshold

    app(DetectVotingFraudAction::class)->execute($edition);

    $alerts = FraudAlert::query()->where('type', FraudAlertType::SharedIp->value)->get();
    expect($alerts)->toHaveCount(1)
        ->and($alerts->first()->signature)->toBe($sharedIp)
        ->and($alerts->first()->ballot_count)->toBe(3)
        ->and($alerts->first()->status)->toBe(FraudAlertStatus::Pending)
        ->and($alerts->first()->ballots()->pluck('ballots.id')->sort()->values()->all())
        ->toBe($clustered->pluck('id')->sort()->values()->all());
});

it('does not record a shared-IP alert below the threshold', function (): void {
    config(['fraud.shared_ip.threshold' => 5]);
    $edition = Edition::factory()->status(EditionStatus::VotingOpen)->create();

    $ip = hash('sha256', '203.0.113.9');
    alertsSeedBallot($edition, $ip);
    alertsSeedBallot($edition, $ip);
    alertsSeedBallot($edition, $ip);

    app(DetectVotingFraudAction::class)->execute($edition);

    expect(FraudAlert::query()->count())->toBe(0);
});

it('excludes already-invalidated ballots from a shared-IP cluster', function (): void {
    config(['fraud.shared_ip.threshold' => 3]);
    $edition = Edition::factory()->status(EditionStatus::VotingOpen)->create();

    $ip = hash('sha256', '203.0.113.9');
    alertsSeedBallot($edition, $ip);
    alertsSeedBallot($edition, $ip);
    $cancelled = alertsSeedBallot($edition, $ip);

    // Invalidate one of the three (the FK is not fillable, so update it directly, as the invalidate action does).
    $batch = InvalidationBatch::factory()->create(['edition_id' => $edition->id]);
    Ballot::query()->whereKey($cancelled->id)->update(['invalidation_batch_id' => $batch->id]);

    app(DetectVotingFraudAction::class)->execute($edition);

    // Only 2 valid ballots remain on that IP → below the threshold of 3 → no alert.
    expect(FraudAlert::query()->count())->toBe(0);
});

it('records a velocity-burst alert for a spike within one window', function (): void {
    config(['fraud.velocity.threshold' => 3, 'fraud.velocity.window_minutes' => 10]);
    $edition = Edition::factory()->status(EditionStatus::VotingOpen)->create();

    // Start at an hour boundary so the whole burst sits inside one epoch-aligned window, whatever the clock.
    $burstAt = now()->startOfHour();
    alertsSeedBallot($edition, hash('sha256', '203.0.113.1'), $burstAt);
    alertsSeedBallot($edition, hash('sha256', '203.0.113.2'), $burstAt->copy()->addMinutes(1));
    alertsSeedBallot($edition, hash('sha256', '203.0.113.3'), $burstAt->copy()->addMinutes(2));
    // A lone submission two hours away — different window, no burst.
    alertsSeedBallot($edition, hash('sha256', '203.0.113.4'), $burstAt->copy()->subHours(2));

    app(DetectVotingFraudAction::class)->execute($edition);

    $alerts = FraudAlert::query()->where('type', FraudAlertType::VelocityBurst->value)->get();
    expect($alerts)->toHaveCount(1)
        ->and($alerts->first()->ballot_count)->toBe(3);
});

it('records an identical-ranking alert for byte-identical ballots', function (): void {
    config(['fraud.identical_ranking.threshold' => 2]);
    $edition = Edition::factory()->status(EditionStatus::VotingOpen)->create();
    $category = Category::factory()->create([
        'edition_id' => $edition->id,
        'nominee_type' => NomineeType::Artist,
    ]);

    // Two identical ballots, one different.
    alertsSeedRankedBallot($edition, $category->id, [10, 20, 30]);
    alertsSeedRankedBallot($edition, $category->id, [10, 20, 30]);
    alertsSeedRankedBallot($edition, $category->id, [30, 20, 10]);

    app(DetectVotingFraudAction::class)->execute($edition);

    $alerts = FraudAlert::query()->where('type', FraudAlertType::IdenticalRanking->value)->get();
    expect($alerts)->toHaveCount(1)
        ->and($alerts->first()->ballot_count)->toBe(2);
});

it('only runs the detectors enabled in config', function (): void {
    // Enable only the velocity detector; a shared-IP cluster that would otherwise fire is ignored.
    config([
        'fraud.enabled_detectors' => [VelocityBurstDetector::class],
        'fraud.shared_ip.threshold' => 3,
        'fraud.velocity.threshold' => 100,
    ]);
    $edition = Edition::factory()->status(EditionStatus::VotingOpen)->create();

    $ip = hash('sha256', '203.0.113.9');
    collect(range(1, 3))->each(fn(): Ballot => alertsSeedBallot($edition, $ip));

    app(DetectVotingFraudAction::class)->execute($edition);

    expect(FraudAlert::query()->count())->toBe(0);
});

it('is idempotent across runs (one alert per signature)', function (): void {
    config(['fraud.shared_ip.threshold' => 3]);
    $edition = Edition::factory()->status(EditionStatus::VotingOpen)->create();

    $ip = hash('sha256', '203.0.113.9');
    collect(range(1, 3))->each(fn(): Ballot => alertsSeedBallot($edition, $ip));

    $action = app(DetectVotingFraudAction::class);
    $action->execute($edition);
    $firstSeenAt = FraudAlert::query()->firstOrFail()->last_detected_at;

    Carbon::setTestNow(now()->addMinutes(5));
    $action->execute($edition);
    Carbon::setTestNow();

    $alerts = FraudAlert::query()->get();
    expect($alerts)->toHaveCount(1)
        ->and($alerts->first()->last_detected_at->greaterThan($firstSeenAt))->toBeTrue()
        ->and($alerts->first()->ballots()->count())->toBe(3);
});

it('raises a new pending alert when a solved signature re-offends', function (): void {
    config(['fraud.shared_ip.threshold' => 3]);
    $edition = Edition::factory()->status(EditionStatus::VotingOpen)->create();
    $ip = hash('sha256', '203.0.113.9');

    $first = collect(range(1, 3))->map(fn(): Ballot => alertsSeedBallot($edition, $ip));
    $action = app(DetectVotingFraudAction::class);
    $action->execute($edition);

    // Resolve: invalidate the cluster's ballots and mark the alert solved.
    $batch = InvalidationBatch::factory()->create(['edition_id' => $edition->id]);
    Ballot::query()->whereIn('id', $first->pluck('id')->all())->update(['invalidation_batch_id' => $batch->id]);
    FraudAlert::query()->firstOrFail()->update(['status' => FraudAlertStatus::Solved]);

    // A fresh wave from the same IP.
    $second = collect(range(1, 3))->map(fn(): Ballot => alertsSeedBallot($edition, $ip));
    $action->execute($edition);

    $forSignature = FraudAlert::query()->where('signature', $ip);
    expect((clone $forSignature)->count())->toBe(2)
        ->and((clone $forSignature)->where('status', FraudAlertStatus::Solved->value)->count())->toBe(1)
        ->and((clone $forSignature)->where('status', FraudAlertStatus::Pending->value)->count())->toBe(1);

    $pending = (clone $forSignature)->where('status', FraudAlertStatus::Pending->value)->firstOrFail();
    expect($pending->ballots()->pluck('ballots.id')->sort()->values()->all())
        ->toBe($second->pluck('id')->sort()->values()->all());
});

it('does not re-raise a dismissed signature', function (): void {
    config(['fraud.shared_ip.threshold' => 3]);
    $edition = Edition::factory()->status(EditionStatus::VotingOpen)->create();
    $ip = hash('sha256', '203.0.113.9');
    collect(range(1, 3))->each(fn(): Ballot => alertsSeedBallot($edition, $ip));

    $action = app(DetectVotingFraudAction::class);
    $action->execute($edition);
    FraudAlert::query()->firstOrFail()->update(['status' => FraudAlertStatus::Dismissed]);

    // The same still-valid ballots are re-detected: stay one dismissed alert, do not raise a new pending.
    $action->execute($edition);

    expect(FraudAlert::query()->count())->toBe(1)
        ->and(FraudAlert::query()->firstOrFail()->status)->toBe(FraudAlertStatus::Dismissed);
});

it('runs via the fraud:detect command', function (): void {
    config(['fraud.shared_ip.threshold' => 3]);
    $edition = Edition::factory()->status(EditionStatus::VotingOpen)->create();
    $ip = hash('sha256', '203.0.113.9');
    collect(range(1, 3))->each(fn(): Ballot => alertsSeedBallot($edition, $ip));

    test()->artisan('fraud:detect')->assertExitCode(0);

    expect(FraudAlert::query()->where('edition_id', $edition->id)->count())->toBe(1);
});

it('lists and shows alerts for authorized roles', function (string $role): void {
    alertsActingAs($role);
    $edition = Edition::factory()->status(EditionStatus::VotingOpen)->create();
    $alert = FraudAlert::factory()->create(['edition_id' => $edition->id]);
    $alert->ballots()->attach(alertsSeedBallot($edition, hash('sha256', '203.0.113.9'))->id);

    getJson("/api/admin/editions/{$edition->id}/fraud-alerts")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'pending');

    getJson("/api/admin/editions/{$edition->id}/fraud-alerts/{$alert->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $alert->id)
        ->assertJsonCount(1, 'data.ballots');
})->with(['fraud_monitor', 'custodian']);

it('forbids and rejects unauthorized alert access', function (): void {
    $edition = Edition::factory()->status(EditionStatus::VotingOpen)->create();
    $alert = FraudAlert::factory()->create(['edition_id' => $edition->id]);

    // Unauthenticated.
    getJson("/api/admin/editions/{$edition->id}/fraud-alerts")->assertStatus(401);

    // Authenticated admin without the fraudMonitoring permission.
    $user = User::factory()->create();
    $user->assignRole(Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])->name);
    Sanctum::actingAs($user);

    getJson("/api/admin/editions/{$edition->id}/fraud-alerts")->assertStatus(403);
    patchJson("/api/admin/editions/{$edition->id}/fraud-alerts/{$alert->id}", ['status' => 'solved'])
        ->assertStatus(403);
});

it('404s showing an alert from another edition', function (): void {
    alertsActingAs('fraud_monitor');
    $editionA = Edition::factory()->status(EditionStatus::VotingOpen)->create();
    $editionB = Edition::factory()->archived()->create();
    $alert = FraudAlert::factory()->create(['edition_id' => $editionA->id]);

    getJson("/api/admin/editions/{$editionB->id}/fraud-alerts/{$alert->id}")->assertStatus(404);
});

it('lets a monitor set an alert status, and detection keeps it', function (): void {
    config(['fraud.shared_ip.threshold' => 3]);
    alertsActingAs('fraud_monitor');
    $edition = Edition::factory()->status(EditionStatus::VotingOpen)->create();
    $ip = hash('sha256', '203.0.113.9');
    collect(range(1, 3))->each(fn(): Ballot => alertsSeedBallot($edition, $ip));

    $action = app(DetectVotingFraudAction::class);
    $action->execute($edition);
    $alert = FraudAlert::query()->firstOrFail();

    patchJson("/api/admin/editions/{$edition->id}/fraud-alerts/{$alert->id}", ['status' => 'dismissed'])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'dismissed');

    expect($alert->refresh()->status)->toBe(FraudAlertStatus::Dismissed);

    // Re-detection refreshes facts but must not resurrect a dismissed alert to pending.
    $action->execute($edition);
    expect($alert->refresh()->status)->toBe(FraudAlertStatus::Dismissed);
});

it('rejects an invalid status', function (): void {
    alertsActingAs('fraud_monitor');
    $edition = Edition::factory()->status(EditionStatus::VotingOpen)->create();
    $alert = FraudAlert::factory()->create(['edition_id' => $edition->id]);

    patchJson("/api/admin/editions/{$edition->id}/fraud-alerts/{$alert->id}", ['status' => 'bogus'])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('status');
});
