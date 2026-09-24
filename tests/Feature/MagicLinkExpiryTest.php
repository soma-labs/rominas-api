<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;
use Rominas\Auth\MagicLink\Actions\SendMagicLinkAction;
use Rominas\Auth\MagicLink\Model\MagicLinkToken;

use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;
use function Pest\Laravel\travelTo;

/**
 * Seed a pending link that expires at a chosen moment; returns the raw token to verify with.
 */
function expirySeedLink(Member $member, Carbon $expiresAt): string
{
    $raw = 'raw-token-' . uniqid();

    MagicLinkToken::query()->create([
        'email' => $member->email,
        'guard' => 'member',
        'token' => Hash::make($raw),
        'created_at' => now(),
        'expires_at' => $expiresAt,
    ]);

    return $raw;
}

function expiryStoredLink(Member $member): MagicLinkToken
{
    return MagicLinkToken::query()->where('email', $member->email)->where('guard', 'member')->firstOrFail();
}

it('issues a link that expires after the lifetime of its e-mail type', function (string $emailAction, string $expiresAt): void {
    Mail::fake();
    travelTo('2026-09-24 12:00:00');
    $member = Member::factory()->invited()->create();

    app(SendMagicLinkAction::class)->execute('member', $member->email, $emailAction);

    expect(expiryStoredLink($member)->expires_at->toDateTimeString())->toBe($expiresAt);
})->with([
    'a sign-in link lasts 15 minutes' => ['academy-magic-link-email', '2026-09-24 12:15:00'],
    'an invitation lasts 48 hours' => ['academy-invitation-email', '2026-09-26 12:00:00'],
]);

it('accepts an invitation link 47 hours after it was issued', function (): void {
    $member = Member::factory()->invited()->create();
    $raw = expirySeedLink($member, now()->addHours(48));

    travel(47)->hours();

    postJson('/api/academy/auth/magic/verify', ['email' => $member->email, 'token' => $raw])
        ->assertStatus(200);
});

it('rejects an invitation link 49 hours after it was issued', function (): void {
    $member = Member::factory()->invited()->create();
    $raw = expirySeedLink($member, now()->addHours(48));

    travel(49)->hours();

    postJson('/api/academy/auth/magic/verify', ['email' => $member->email, 'token' => $raw])
        ->assertStatus(422);

    expect($member->refresh()->status)->toBe(MemberStatus::Invited);
});

it('accepts a sign-in link 14 minutes after it was issued', function (): void {
    $member = Member::factory()->invited()->create();
    $raw = expirySeedLink($member, now()->addMinutes(15));

    travel(14)->minutes();

    postJson('/api/academy/auth/magic/verify', ['email' => $member->email, 'token' => $raw])
        ->assertStatus(200);
});

it('rejects a sign-in link 16 minutes after it was issued', function (): void {
    $member = Member::factory()->invited()->create();
    $raw = expirySeedLink($member, now()->addMinutes(15));

    travel(16)->minutes();

    postJson('/api/academy/auth/magic/verify', ['email' => $member->email, 'token' => $raw])
        ->assertStatus(422);

    expect($member->refresh()->status)->toBe(MemberStatus::Invited);
});

it('replaces an unused invitation with a sign-in link that lasts only 15 minutes', function (): void {
    Mail::fake();
    travelTo('2026-09-24 12:00:00');
    $member = Member::factory()->invited()->create();
    $action = app(SendMagicLinkAction::class);

    $action->execute('member', $member->email, 'academy-invitation-email');
    $invitationToken = expiryStoredLink($member)->token;

    travelTo('2026-09-24 12:02:00');
    $action->execute('member', $member->email, 'academy-magic-link-email');

    $signIn = expiryStoredLink($member);
    expect($signIn->token)->not->toBe($invitationToken);
    expect($signIn->expires_at->toDateTimeString())->toBe('2026-09-24 12:17:00');
});
