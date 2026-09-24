<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
use Rominas\Users\Model\User;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

/**
 * A payload whose six datetimes satisfy the strict ordering
 * starts_at < nominations_start_at < nominations_end_at < voting_start_at < voting_end_at < ends_at.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validEditionPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Rominas 2026',
        'starts_at' => '2026-01-01 00:00:00',
        'nominations_start_at' => '2026-01-15 00:00:00',
        'nominations_end_at' => '2026-02-15 00:00:00',
        'voting_start_at' => '2026-03-01 00:00:00',
        'voting_end_at' => '2026-04-01 00:00:00',
        'ends_at' => '2026-06-01 00:00:00',
    ], $overrides);
}

it('lets a super_admin create a draft edition', function (): void {
    actingAsSuperAdmin();

    postJson('/api/admin/editions', validEditionPayload())
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.nominations_start_at', fn($v) => $v !== null);

    expect(Edition::query()->where('name', 'Rominas 2026')->exists())->toBeTrue();
});

it('forbids a second active edition', function (): void {
    actingAsSuperAdmin();
    Edition::factory()->create(); // draft = active

    postJson('/api/admin/editions', validEditionPayload(['name' => 'Rominas 2027']))
        ->assertStatus(422);
});

it('allows creating a new edition once the previous is archived', function (): void {
    actingAsSuperAdmin();
    Edition::factory()->archived()->create();

    postJson('/api/admin/editions', validEditionPayload(['name' => 'Rominas 2027']))
        ->assertStatus(201);
});

it('rejects a timeline that is out of order', function (): void {
    actingAsSuperAdmin();

    // voting_start_at before nominations_end_at breaks the chain.
    postJson('/api/admin/editions', validEditionPayload([
        'voting_start_at' => '2026-02-01 00:00:00',
    ]))->assertStatus(422)->assertJsonValidationErrorFor('voting_start_at');
});

it('rejects equal adjacent timestamps (strict ordering)', function (): void {
    actingAsSuperAdmin();

    postJson('/api/admin/editions', validEditionPayload([
        'nominations_start_at' => '2026-01-01 00:00:00', // equal to starts_at
    ]))->assertStatus(422)->assertJsonValidationErrorFor('nominations_start_at');
});

it('updates an edition\'s vote weights', function (): void {
    actingAsSuperAdmin();
    $edition = Edition::factory()->create();

    patchJson("/api/admin/editions/{$edition->id}", validEditionPayload([
        'name' => $edition->name,
        'academy_vote_weight' => 70,
        'public_vote_weight' => 30,
    ]))
        ->assertStatus(200)
        ->assertJsonPath('data.academy_vote_weight', 70)
        ->assertJsonPath('data.public_vote_weight', 30);

    expect($edition->fresh()->academy_vote_weight)->toBe(70);
});

it('keeps the current weights when an update omits them', function (): void {
    actingAsSuperAdmin();
    $edition = Edition::factory()->create(['academy_vote_weight' => 55, 'public_vote_weight' => 45]);

    patchJson("/api/admin/editions/{$edition->id}", validEditionPayload(['name' => $edition->name]))
        ->assertStatus(200);

    expect($edition->fresh()->academy_vote_weight)->toBe(55)
        ->and($edition->fresh()->public_vote_weight)->toBe(45);
});

it('rejects both vote weights being zero', function (): void {
    actingAsSuperAdmin();
    $edition = Edition::factory()->create();

    patchJson("/api/admin/editions/{$edition->id}", validEditionPayload([
        'name' => $edition->name,
        'academy_vote_weight' => 0,
        'public_vote_weight' => 0,
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('academy_vote_weight');
});

it('performs a valid lifecycle transition', function (): void {
    actingAsSuperAdmin();
    $edition = Edition::factory()->create(); // draft

    patchJson("/api/admin/editions/{$edition->id}/status", [
        'status' => EditionStatus::NominationsOpen->value,
    ])->assertStatus(200)
        ->assertJsonPath('data.status', 'nominations_open');
});

it('rejects the removed invitations_sent status', function (): void {
    actingAsSuperAdmin();
    $edition = Edition::factory()->create(); // draft

    patchJson("/api/admin/editions/{$edition->id}/status", [
        'status' => 'invitations_sent',
    ])->assertStatus(422);
});

it('rejects an illegal lifecycle transition', function (): void {
    actingAsSuperAdmin();
    $edition = Edition::factory()->create(); // draft

    patchJson("/api/admin/editions/{$edition->id}/status", [
        'status' => EditionStatus::VotingOpen->value,
    ])->assertStatus(422);
});

it('deletes a draft edition but not one already underway', function (): void {
    actingAsSuperAdmin();

    $draft = Edition::factory()->create();
    deleteJson("/api/admin/editions/{$draft->id}")->assertStatus(200);

    $live = Edition::factory()->status(EditionStatus::NominationsOpen)->create();
    deleteJson("/api/admin/editions/{$live->id}")->assertStatus(422);
});

it('forbids editions management without permission', function (): void {
    Sanctum::actingAs(User::factory()->create());

    postJson('/api/admin/editions', validEditionPayload())->assertStatus(403);
});

it('requires authentication for editions', function (): void {
    postJson('/api/admin/editions', [])->assertStatus(401);
});
