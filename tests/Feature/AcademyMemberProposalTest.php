<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\MemberProposal\Enums\MemberProposalStatus;
use Rominas\Academy\MemberProposal\Model\MemberProposal;
use Rominas\Users\Model\User;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function actAsMember(): Member
{
    $member = Member::factory()->active()->create();
    Sanctum::actingAs($member, ['member'], 'member');

    return $member;
}

it('lets a member propose a future member with contact details', function (): void {
    actAsMember();

    postJson('/api/academy/proposals', [
        'name' => 'Ioana Munteanu',
        'email' => 'ioana@example.test',
        'position' => 'Producer',
        'company' => 'Studio Nord',
        'phone' => '+40 720 000 000',
        'reason' => 'Respected producer.',
    ])->assertStatus(201)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.email', 'ioana@example.test')
        ->assertJsonPath('data.position', 'Producer')
        ->assertJsonPath('data.company', 'Studio Nord')
        ->assertJsonPath('data.phone', '+40 720 000 000');

    expect(MemberProposal::query()->where('email', 'ioana@example.test')->exists())->toBeTrue();
});

it('enforces the per-member lifetime cap on proposals', function (): void {
    config(['academy.max_proposals_per_member' => 2]);
    $member = actAsMember();
    MemberProposal::factory()->count(2)->create(['proposed_by_member_id' => $member->id]);

    postJson('/api/academy/proposals', ['name' => 'Over Limit', 'email' => 'over@example.test'])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('proposals');

    expect(MemberProposal::query()->where('email', 'over@example.test')->exists())->toBeFalse();
});

it('lists only the proposing member\'s own proposals', function (): void {
    $member = actAsMember();
    MemberProposal::factory()->count(2)->create(['proposed_by_member_id' => $member->id]);
    MemberProposal::factory()->create(); // someone else's

    getJson('/api/academy/proposals')
        ->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

it('lets a member withdraw their own pending proposal', function (): void {
    $member = actAsMember();
    $proposal = MemberProposal::factory()->create(['proposed_by_member_id' => $member->id]);

    deleteJson("/api/academy/proposals/{$proposal->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    expect(MemberProposal::query()->whereKey($proposal->id)->exists())->toBeFalse();
});

it('forbids withdrawing another member\'s proposal', function (): void {
    actAsMember();
    $proposal = MemberProposal::factory()->create(); // another member's

    deleteJson("/api/academy/proposals/{$proposal->id}")->assertStatus(403);

    expect(MemberProposal::query()->whereKey($proposal->id)->exists())->toBeTrue();
});

it('cannot withdraw a proposal that is no longer pending', function (): void {
    $member = actAsMember();
    $proposal = MemberProposal::factory()->approved()->create(['proposed_by_member_id' => $member->id]);

    deleteJson("/api/academy/proposals/{$proposal->id}")->assertStatus(422);
});

it('rejects proposing an email that is already a member', function (): void {
    actAsMember();
    Member::factory()->create(['email' => 'existing@example.test']);

    postJson('/api/academy/proposals', ['name' => 'Dup', 'email' => 'existing@example.test'])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('email');
});

it('rejects proposing an email that already has a pending proposal', function (): void {
    actAsMember();
    MemberProposal::factory()->create(['email' => 'pending@example.test']);

    postJson('/api/academy/proposals', ['name' => 'Dup', 'email' => 'pending@example.test'])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('email');
});

it('requires member authentication to propose', function (): void {
    postJson('/api/academy/proposals', ['name' => 'X', 'email' => 'x@example.test'])
        ->assertStatus(401);
});

it('lets an admin list proposals', function (): void {
    actingAsSuperAdmin();
    MemberProposal::factory()->count(3)->create();

    getJson('/api/admin/member-proposals')
        ->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

it('creates an invited member when an admin approves a proposal', function (): void {
    actingAsSuperAdmin();
    $proposal = MemberProposal::factory()->create([
        'name' => 'Approved Person',
        'email' => 'approved@example.test',
    ]);

    postJson("/api/admin/member-proposals/{$proposal->id}/approve", ['note' => 'Looks great'])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'approved');

    $member = Member::query()->where('email', 'approved@example.test')->first();
    expect($member)->not->toBeNull();
    expect($member->status)->toBe(MemberStatus::Invited);

    $proposal->refresh();
    expect($proposal->status)->toBe(MemberProposalStatus::Approved);
    expect($proposal->member_id)->toBe($member->id);
});

it('cannot approve a proposal that is not pending', function (): void {
    actingAsSuperAdmin();
    $proposal = MemberProposal::factory()->rejected()->create();

    postJson("/api/admin/member-proposals/{$proposal->id}/approve")->assertStatus(422);
});

it('rejects a proposal without creating a member', function (): void {
    actingAsSuperAdmin();
    $proposal = MemberProposal::factory()->create(['email' => 'nope@example.test']);

    postJson("/api/admin/member-proposals/{$proposal->id}/reject", ['note' => 'Not this cycle'])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'rejected');

    expect(Member::query()->where('email', 'nope@example.test')->exists())->toBeFalse();
    expect($proposal->refresh()->review_note)->toBe('Not this cycle');
});

it('forbids a user without the memberProposals permission', function (): void {
    Sanctum::actingAs(User::factory()->create());

    getJson('/api/admin/member-proposals')->assertStatus(403);
});
