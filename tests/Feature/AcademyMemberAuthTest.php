<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;
use Rominas\Auth\MagicLink\Actions\SendMagicLinkAction;
use Rominas\Auth\MagicLink\Model\MagicLinkToken;
use Rominas\Users\Model\User;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * Seed a pending magic-link row for a member and return the raw token to verify with.
 */
function seedMagicLink(Member $member, ?string $createdAt = null, ?string $expiresAt = null): string
{
    $raw = 'raw-token-' . uniqid();

    MagicLinkToken::query()->create([
        'email' => $member->email,
        'guard' => 'member',
        'token' => Hash::make($raw),
        'created_at' => $createdAt ?? now(),
        'expires_at' => $expiresAt ?? now()->addMinutes(15),
    ]);

    return $raw;
}

it('requests a magic link and stores a hashed token for a known member', function (): void {
    Mail::fake();
    $member = Member::factory()->active()->create();

    postJson('/api/academy/auth/magic/request', ['email' => $member->email])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $row = MagicLinkToken::query()->where('email', $member->email)->where('guard', 'member')->first();
    expect($row)->not->toBeNull();
    expect($row->token)->not->toBe(''); // stored hashed, never the plaintext
});

it('does not create a token for an unknown email (no existence leak)', function (): void {
    Mail::fake();

    postJson('/api/academy/auth/magic/request', ['email' => 'nobody@example.test'])
        ->assertStatus(200);

    expect(MagicLinkToken::query()->count())->toBe(0);
});

it('keeps the same token within the 60s resend cooldown', function (): void {
    Mail::fake();
    $member = Member::factory()->active()->create();
    $action = app(SendMagicLinkAction::class);

    $action->execute('member', $member->email, 'academy-magic-link-email');
    $first = MagicLinkToken::query()->where('email', $member->email)->where('guard', 'member')->firstOrFail();

    $action->execute('member', $member->email, 'academy-magic-link-email');
    $second = MagicLinkToken::query()->where('email', $member->email)->where('guard', 'member')->firstOrFail();

    expect($second->token)->toBe($first->token);
});

it('rate limits magic-link requests', function (): void {
    Mail::fake();
    $member = Member::factory()->active()->create();

    for ($i = 0; $i < 5; $i++) {
        postJson('/api/academy/auth/magic/request', ['email' => $member->email])->assertStatus(200);
    }

    postJson('/api/academy/auth/magic/request', ['email' => $member->email])->assertStatus(429);
});

it('verifies a valid magic link, activates the member, and issues a token', function (): void {
    $member = Member::factory()->invited()->create();
    $raw = seedMagicLink($member);

    postJson('/api/academy/auth/magic/verify', ['email' => $member->email, 'token' => $raw])
        ->assertStatus(200)
        ->assertJsonPath('memberId', $member->id)
        ->assertJsonPath('token', fn($v) => is_string($v) && $v !== '');

    $member->refresh();
    expect($member->status)->toBe(MemberStatus::Active);
    expect($member->email_verified_at)->not->toBeNull();
    expect($member->activated_at)->not->toBeNull();

    // Single-use: the token row is consumed.
    expect(MagicLinkToken::query()->where('email', $member->email)->count())->toBe(0);
});

it('rejects an invalid token', function (): void {
    $member = Member::factory()->invited()->create();
    seedMagicLink($member);

    postJson('/api/academy/auth/magic/verify', ['email' => $member->email, 'token' => 'wrong-token'])
        ->assertStatus(422);

    expect($member->refresh()->status)->toBe(MemberStatus::Invited);
});

it('rejects an expired token', function (): void {
    $member = Member::factory()->invited()->create();
    $raw = seedMagicLink($member, now()->subMinutes(20)->toDateTimeString(), now()->subMinutes(5)->toDateTimeString());

    postJson('/api/academy/auth/magic/verify', ['email' => $member->email, 'token' => $raw])
        ->assertStatus(422);
});

it('is single-use — a second verify with the same token fails', function (): void {
    $member = Member::factory()->invited()->create();
    $raw = seedMagicLink($member);

    postJson('/api/academy/auth/magic/verify', ['email' => $member->email, 'token' => $raw])->assertStatus(200);
    postJson('/api/academy/auth/magic/verify', ['email' => $member->email, 'token' => $raw])->assertStatus(422);
});

it('rejects verify for an unknown email', function (): void {
    postJson('/api/academy/auth/magic/verify', ['email' => 'nobody@example.test', 'token' => 'anything'])
        ->assertStatus(422);
});

it('rejects a suspended member even with a valid token', function (): void {
    $member = Member::factory()->suspended()->create();
    $raw = seedMagicLink($member);

    postJson('/api/academy/auth/magic/verify', ['email' => $member->email, 'token' => $raw])
        ->assertStatus(422);

    expect($member->refresh()->status)->toBe(MemberStatus::Suspended);
});

it('returns the authenticated member from /me', function (): void {
    $member = Member::factory()->active()->create();
    Sanctum::actingAs($member, ['member'], 'member');

    getJson('/api/academy/me')
        ->assertStatus(200)
        ->assertJsonPath('data.id', $member->id)
        ->assertJsonPath('data.email', $member->email);
});

it('logs out by revoking the current member token', function (): void {
    $member = Member::factory()->active()->create();
    $token = $member->createToken('login', ['member'])->plainTextToken;

    postJson('/api/academy/logout', [], ['Authorization' => 'Bearer ' . $token])
        ->assertStatus(200);

    expect($member->tokens()->count())->toBe(0);
});

it('rejects an admin User token on the member guard', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('admin')->plainTextToken;

    getJson('/api/academy/me', ['Authorization' => 'Bearer ' . $token])
        ->assertStatus(401);
});
