<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;
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
        ->assertJsonPath('data.email', 'ana@example.test');

    expect(Member::query()->where('email', 'ana@example.test')->exists())->toBeTrue();
});

it('lets an admin list members', function (): void {
    actingAsSuperAdmin();
    Member::factory()->count(3)->create();

    getJson('/api/admin/members')
        ->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

it('lets an admin update a member', function (): void {
    actingAsSuperAdmin();
    $member = Member::factory()->create(['name' => 'Old Name']);

    patchJson('/api/admin/members/' . $member->id, [
        'name' => 'New Name',
        'email' => $member->email,
    ])->assertStatus(200)
        ->assertJsonPath('data.name', 'New Name');
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
