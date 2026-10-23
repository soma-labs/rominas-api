<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\MemberProposal\Model\MemberProposal;
use Rominas\Academy\Nomination\Model\Nomination;
use Rominas\Academy\Nomination\Model\NominationRanking;
use Rominas\Editions\Model\Edition;
use Rominas\Users\Model\User;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

it('lets an admin create a member awaiting invitation', function (): void {
    actingAsSuperAdmin();

    postJson('/api/admin/members', [
        'name' => 'Ana Popescu',
        'email' => 'ana@example.test',
    ])->assertStatus(201)
        ->assertJsonPath('data.status', 'awaiting_invitation')
        ->assertJsonPath('data.email', 'ana@example.test')
        ->assertJsonPath('data.is_founding_member', false);

    expect(Member::query()->where('email', 'ana@example.test')->exists())->toBeTrue();
});

it('lets an admin create a founding member', function (): void {
    actingAsSuperAdmin();

    postJson('/api/admin/members', [
        'name' => 'Ana Popescu',
        'email' => 'ana@example.test',
        'is_founding_member' => true,
    ])->assertStatus(201)
        ->assertJsonPath('data.is_founding_member', true);

    expect(Member::query()->where('email', 'ana@example.test')->value('is_founding_member'))->toBeTrue();
});

it('stores the optional contact details of a new member', function (): void {
    actingAsSuperAdmin();

    postJson('/api/admin/members', [
        'name' => 'Ana Popescu',
        'email' => 'ana@example.test',
        'position' => 'Producer',
        'company' => 'Studio Nord',
        'phone' => '+40 720 000 000',
    ])->assertStatus(201)
        ->assertJsonPath('data.position', 'Producer')
        ->assertJsonPath('data.company', 'Studio Nord')
        ->assertJsonPath('data.phone', '+40 720 000 000');

    $member = Member::query()->where('email', 'ana@example.test')->firstOrFail();
    expect($member->position)->toBe('Producer');
    expect($member->company)->toBe('Studio Nord');
    expect($member->phone)->toBe('+40 720 000 000');
});

it('leaves the contact details empty when a new member has none', function (): void {
    actingAsSuperAdmin();

    postJson('/api/admin/members', [
        'name' => 'Ana Popescu',
        'email' => 'ana@example.test',
    ])->assertStatus(201)
        ->assertJsonPath('data.position', null)
        ->assertJsonPath('data.company', null)
        ->assertJsonPath('data.phone', null);
});

it('rejects a contact detail longer than 255 characters', function (string $field): void {
    actingAsSuperAdmin();

    postJson('/api/admin/members', [
        'name' => 'Ana Popescu',
        'email' => 'ana@example.test',
        $field => str_repeat('a', 256),
    ])->assertStatus(422)->assertJsonValidationErrorFor($field);
})->with(['position', 'company', 'phone']);

it('lets an admin list members', function (): void {
    actingAsSuperAdmin();
    Member::factory()->count(3)->create();

    getJson('/api/admin/members')
        ->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

it('lists the newest members first by default', function (): void {
    actingAsSuperAdmin();
    $older = Member::factory()->create(['name' => 'Ana']);
    $newer = Member::factory()->create(['name' => 'Zoe']);

    getJson('/api/admin/members')
        ->assertStatus(200)
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id);
});

it('filters the members by their nominations in the active edition', function (string $query, string $expected): void {
    actingAsSuperAdmin();
    $edition = Edition::factory()->create();
    $members = [
        'idle' => Member::factory()->create(),
        'started' => Member::factory()->create(),
        'submitted' => Member::factory()->create(),
    ];
    NominationRanking::factory()->for(Nomination::factory()->for($members['started'])->for($edition))->create();
    Nomination::factory()->submitted()->for($members['submitted'])->for($edition)->create();

    getJson('/api/admin/members?' . $query)
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $members[$expected]->id);
})->with([
    'started' => ['startedNominations=yes&submittedNominations=no', 'started'],
    'submitted' => ['submittedNominations=yes', 'submitted'],
    'neither' => ['startedNominations=no&submittedNominations=no', 'idle'],
]);

it('filters the members by whether they proposed someone', function (string $value, bool $proposed): void {
    actingAsSuperAdmin();
    $proposer = Member::factory()->create();
    $other = Member::factory()->create();
    MemberProposal::factory()->create(['proposed_by_member_id' => $proposer->id]);

    getJson('/api/admin/members?submittedMemberProposals=' . $value)
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $proposed ? $proposer->id : $other->id);
})->with([
    'proposed' => ['yes', true],
    'not proposed' => ['no', false],
]);

it('filters the members by whether they are founding members', function (string $value, bool $founding): void {
    actingAsSuperAdmin();
    $foundingMember = Member::factory()->founding()->create();
    $other = Member::factory()->create();

    getJson('/api/admin/members?foundingMember=' . $value)
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $founding ? $foundingMember->id : $other->id);
})->with([
    'founding' => ['yes', true],
    'not founding' => ['no', false],
]);

it('refuses a yes-or-no list filter with any other value', function (string $filter): void {
    actingAsSuperAdmin();

    getJson('/api/admin/members?' . $filter . '=maybe')
        ->assertStatus(422)
        ->assertJsonValidationErrorFor($filter);
})->with(['startedNominations', 'submittedNominations', 'submittedMemberProposals', 'foundingMember']);

it('lets an admin update a member', function (): void {
    actingAsSuperAdmin();
    $member = Member::factory()->create(['name' => 'Old Name']);

    patchJson('/api/admin/members/' . $member->id, [
        'name' => 'New Name',
        'email' => $member->email,
    ])->assertStatus(200)
        ->assertJsonPath('data.name', 'New Name');
});

it('lets an admin change and clear a member\'s contact details', function (): void {
    actingAsSuperAdmin();
    $member = Member::factory()->create([
        'position' => 'Producer',
        'company' => 'Studio Nord',
        'phone' => '+40 720 000 000',
    ]);

    patchJson('/api/admin/members/' . $member->id, [
        'name' => $member->name,
        'email' => $member->email,
        'position' => 'Manager',
        'phone' => null,
    ])->assertStatus(200)
        ->assertJsonPath('data.position', 'Manager')
        ->assertJsonPath('data.company', 'Studio Nord')
        ->assertJsonPath('data.phone', null);

    $member->refresh();
    expect($member->position)->toBe('Manager');
    expect($member->company)->toBe('Studio Nord');
    expect($member->phone)->toBeNull();
});

it('lets an admin change whether a member is a founding member', function (): void {
    actingAsSuperAdmin();
    $member = Member::factory()->create();

    patchJson('/api/admin/members/' . $member->id, [
        'name' => $member->name,
        'email' => $member->email,
        'is_founding_member' => true,
    ])->assertStatus(200)
        ->assertJsonPath('data.is_founding_member', true);

    expect($member->refresh()->is_founding_member)->toBeTrue();
});

it('keeps a member a founding member when an update leaves the flag out', function (): void {
    actingAsSuperAdmin();
    $member = Member::factory()->founding()->create();

    patchJson('/api/admin/members/' . $member->id, [
        'name' => 'New Name',
        'email' => $member->email,
    ])->assertStatus(200)
        ->assertJsonPath('data.is_founding_member', true);
});

it('lets an admin suspend a member via a status update', function (): void {
    actingAsSuperAdmin();
    $member = Member::factory()->active()->create();

    patchJson('/api/admin/members/' . $member->id, [
        'name' => $member->name,
        'email' => $member->email,
        'status' => MemberStatus::Suspended->value,
    ])->assertStatus(200)
        ->assertJsonPath('data.status', 'suspended');

    expect($member->refresh()->status)->toBe(MemberStatus::Suspended);
});

it('lets an admin delete a member', function (): void {
    actingAsSuperAdmin();
    $member = Member::factory()->create();

    deleteJson('/api/admin/members/' . $member->id)
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    expect(Member::query()->whereKey($member->id)->exists())->toBeFalse();
});

it('rejects a duplicate member email', function (): void {
    actingAsSuperAdmin();
    Member::factory()->create(['email' => 'dupe@example.test']);

    postJson('/api/admin/members', [
        'name' => 'Someone',
        'email' => 'dupe@example.test',
    ])->assertStatus(422)->assertJsonValidationErrorFor('email');
});

it('forbids a user without the members permission', function (): void {
    Sanctum::actingAs(User::factory()->create());

    getJson('/api/admin/members')->assertStatus(403);
});
