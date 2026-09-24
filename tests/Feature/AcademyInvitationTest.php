<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;
use Rominas\Auth\MagicLink\Jobs\SendMagicLinkJob;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;

use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

it('sends invitations on demand to every member awaiting one', function (): void {
    actingAsSuperAdmin();
    Bus::fake();

    Member::factory()->count(2)->awaitingInvitation()->create();
    Member::factory()->invited()->create(); // already invited — should not be re-sent
    Member::factory()->active()->create(); // already active — should not be invited

    postJson('/api/admin/members/invitations')
        ->assertStatus(200)
        ->assertJsonPath('invited', 2);

    Bus::assertDispatchedTimes(SendMagicLinkJob::class, 2);
});

it('moves bulk-invited members to invited and stamps invited_at', function (): void {
    actingAsSuperAdmin();
    Bus::fake();

    $member = Member::factory()->awaitingInvitation()->create();

    postJson('/api/admin/members/invitations')->assertStatus(200);

    $member->refresh();
    expect($member->status)->toBe(MemberStatus::Invited)
        ->and($member->invited_at)->not->toBeNull();
});

it('moves a single member from awaiting invitation to invited', function (): void {
    actingAsSuperAdmin();
    Bus::fake();

    $member = Member::factory()->awaitingInvitation()->create();

    postJson("/api/admin/members/{$member->id}/invite")->assertStatus(200);

    Bus::assertDispatchedTimes(SendMagicLinkJob::class, 1);
    $member->refresh();
    expect($member->status)->toBe(MemberStatus::Invited)
        ->and($member->invited_at)->not->toBeNull();
});

it('re-sends a single invitation without changing an active member\'s status', function (): void {
    actingAsSuperAdmin();
    Bus::fake();

    $member = Member::factory()->active()->create();

    postJson("/api/admin/members/{$member->id}/invite")->assertStatus(200);

    Bus::assertDispatchedTimes(SendMagicLinkJob::class, 1);
    expect($member->refresh()->status)->toBe(MemberStatus::Active);
});

it('does not send invitations when an edition opens nominations', function (): void {
    actingAsSuperAdmin();
    Bus::fake();

    $edition = Edition::factory()->create(); // draft
    $member = Member::factory()->awaitingInvitation()->create();

    patchJson('/api/admin/editions/' . $edition->id . '/status', [
        'status' => EditionStatus::NominationsOpen->value,
    ])->assertStatus(200);

    Bus::assertNotDispatched(SendMagicLinkJob::class);
    expect($member->refresh()->status)->toBe(MemberStatus::AwaitingInvitation);
});
