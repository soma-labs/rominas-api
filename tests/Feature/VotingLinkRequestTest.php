<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
use Rominas\Voting\Jobs\SendVotingLinkJob;
use Rominas\Voting\Model\Ballot;
use Rominas\Voting\Support\VoterHasher;

use function Pest\Laravel\postJson;

/**
 * An edition in `voting_open` whose voting window contains "now", so the resolve gate passes.
 */
function votingOpenEdition(): Edition
{
    $now = now();

    return Edition::factory()->status(EditionStatus::VotingOpen)->create([
        'starts_at' => $now->copy()->subMonths(2),
        'nominations_start_at' => $now->copy()->subWeeks(6),
        'nominations_end_at' => $now->copy()->subWeeks(3),
        'voting_start_at' => $now->copy()->subDay(),
        'voting_end_at' => $now->copy()->addWeek(),
        'ends_at' => $now->copy()->addMonths(2),
    ]);
}

/**
 * Turn e-mail validation on and make ZeroBounce answer every address with the given verdict. Any other
 * outbound request fails the test.
 */
function fakeZeroBounceVerdict(string $status, string $subStatus = ''): void
{
    config(['services.zerobounce.api_key' => 'test-key']);

    Http::preventStrayRequests();
    Http::fake([
        'api-eu.zerobounce.net/v2/validate*' => Http::response([
            'address' => 'voter@example.test',
            'status' => $status,
            'sub_status' => $subStatus,
        ]),
    ]);
}

it('issues a single-use ballot and queues the link email during open voting', function (): void {
    Bus::fake();
    $edition = votingOpenEdition();

    postJson('/api/voting/request', ['email' => 'Voter@Example.Test'])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $ballot = Ballot::query()->forEdition($edition)->first();
    expect($ballot)->not->toBeNull();
    // Identity is pseudonymized — the row stores the email HASH, never the plaintext.
    expect($ballot->email_hash)->toBe(VoterHasher::emailHash('Voter@Example.Test'));
    expect($ballot->getAttributes())->not->toHaveKey('email');
    expect($ballot->ip_hash)->toBeNull();
    expect($ballot->expires_at->equalTo($edition->voting_end_at))->toBeTrue();

    Bus::assertDispatched(SendVotingLinkJob::class);
});

it('issues no second ballot and leaks nothing on a repeat request', function (): void {
    Bus::fake();
    $edition = votingOpenEdition();

    postJson('/api/voting/request', ['email' => 'voter@example.test'])->assertStatus(200);
    postJson('/api/voting/request', ['email' => 'voter@example.test'])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    expect(Ballot::query()->forEdition($edition)->count())->toBe(1);
    Bus::assertDispatchedTimes(SendVotingLinkJob::class, 1);
});

it('normalizes casing/whitespace to one identity', function (): void {
    Bus::fake();
    $edition = votingOpenEdition();

    postJson('/api/voting/request', ['email' => 'voter@example.test'])->assertStatus(200);
    postJson('/api/voting/request', ['email' => '  VOTER@example.test '])->assertStatus(200);

    expect(Ballot::query()->forEdition($edition)->count())->toBe(1);
});

it('refuses to issue a link when voting is not open', function (): void {
    Bus::fake();
    Edition::factory()->status(EditionStatus::NominationsClosed)->create();

    postJson('/api/voting/request', ['email' => 'voter@example.test'])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('voting');

    expect(Ballot::query()->count())->toBe(0);
    Bus::assertNotDispatched(SendVotingLinkJob::class);
});

it('validates the email', function (): void {
    votingOpenEdition();

    postJson('/api/voting/request', ['email' => 'not-an-email'])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('email');
});

it('throttles repeated link requests', function (): void {
    Bus::fake();
    votingOpenEdition();

    for ($i = 0; $i < 5; $i++) {
        postJson('/api/voting/request', ['email' => 'voter@example.test'])->assertStatus(200);
    }

    postJson('/api/voting/request', ['email' => 'voter@example.test'], ['Accept-Language' => 'ro'])
        ->assertStatus(429)
        ->assertJsonPath('message', 'Prea multe cereri. Așteaptă un minut și încearcă din nou.');
});

it('answers the generic link message in Romanian when Accept-Language is ro', function (): void {
    Bus::fake();
    votingOpenEdition();

    postJson('/api/voting/request', ['email' => 'voter@example.test'], ['Accept-Language' => 'ro'])
        ->assertStatus(200)
        ->assertJsonPath('message', 'Dacă adresa este validă, vei primi un link de vot.');
});

it('explains in Romanian that voting is not open when Accept-Language is ro', function (): void {
    Bus::fake();
    Edition::factory()->status(EditionStatus::NominationsClosed)->create();

    postJson('/api/voting/request', ['email' => 'voter@example.test'], ['Accept-Language' => 'ro'])
        ->assertStatus(422)
        ->assertJsonPath('errors.voting.0', 'Votul nu este deschis.');
});

it('issues the link and records the verdict for an address ZeroBounce does not rule out', function (
    string $status,
    string $subStatus,
    ?string $storedSubStatus,
    string $decision,
): void {
    Bus::fake([SendVotingLinkJob::class]);
    $edition = votingOpenEdition();
    fakeZeroBounceVerdict($status, $subStatus);

    postJson('/api/voting/request', ['email' => 'voter@example.test'])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $ballot = Ballot::query()->forEdition($edition)->sole();
    expect($ballot->email_check_status)->toBe($status)
        ->and($ballot->email_check_sub_status)->toBe($storedSubStatus)
        ->and($ballot->email_check_decision?->value)->toBe($decision);
    Bus::assertDispatchedTimes(SendVotingLinkJob::class, 1);
})->with([
    'a valid address' => ['valid', '', null, 'accepted'],
    'a valid alias' => ['valid', 'alias_address', 'alias_address', 'accepted'],
    'a catch-all domain' => ['catch-all', '', null, 'accepted_with_note'],
    'a role address' => ['do_not_mail', 'role_based', 'role_based', 'accepted_with_note'],
    'a sub-status the table does not list' => ['unknown', 'greylisted', 'greylisted', 'accepted_with_note'],
    'a status the table does not list' => ['brand_new_status', '', null, 'accepted_with_note'],
]);

it('returns 422 on email and issues nothing for an address ZeroBounce rules out', function (
    string $status,
    string $subStatus,
): void {
    Bus::fake([SendVotingLinkJob::class]);
    votingOpenEdition();
    fakeZeroBounceVerdict($status, $subStatus);

    postJson('/api/voting/request', ['email' => 'voter@example.test'])
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'This email address cannot be used to vote. Please use a different address.');

    expect(Ballot::query()->count())->toBe(0);
    Bus::assertNotDispatched(SendVotingLinkJob::class);
})->with([
    'a disposable address' => ['do_not_mail', 'disposable'],
    'an AI mailbox on a catch-all domain' => ['catch-all', 'ai_agent_mailbox'],
    'a spam trap' => ['spamtrap', ''],
    'an invalid address with an unlisted sub-status' => ['invalid', 'mailbox_not_found'],
]);

it('explains the rejected address in Romanian when Accept-Language is ro', function (): void {
    Bus::fake([SendVotingLinkJob::class]);
    votingOpenEdition();
    fakeZeroBounceVerdict('do_not_mail', 'disposable');

    postJson('/api/voting/request', ['email' => 'voter@example.test'], ['Accept-Language' => 'ro'])
        ->assertStatus(422)
        ->assertJsonPath(
            'errors.email.0',
            'Această adresă de e-mail nu poate fi folosită pentru vot. Te rugăm să folosești o altă adresă.',
        );

    Bus::assertNotDispatched(SendVotingLinkJob::class);
});

it('refuses a rejected address again without asking ZeroBounce a second time', function (): void {
    Bus::fake([SendVotingLinkJob::class]);
    votingOpenEdition();
    fakeZeroBounceVerdict('do_not_mail', 'disposable');

    postJson('/api/voting/request', ['email' => 'voter@example.test'])->assertStatus(422);
    postJson('/api/voting/request', ['email' => 'voter@example.test'])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('email');

    Http::assertSentCount(1);
    Bus::assertNotDispatched(SendVotingLinkJob::class);
});

it('still issues the link, unchecked, when ZeroBounce cannot answer', function (Closure $zeroBounceAnswer): void {
    Bus::fake([SendVotingLinkJob::class]);
    $edition = votingOpenEdition();
    config(['services.zerobounce.api_key' => 'test-key']);
    Http::preventStrayRequests();
    Http::fake(['api-eu.zerobounce.net/v2/validate*' => $zeroBounceAnswer()]);

    postJson('/api/voting/request', ['email' => 'voter@example.test'])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $ballot = Ballot::query()->forEdition($edition)->sole();
    expect($ballot->email_check_status)->toBeNull()
        ->and($ballot->email_check_sub_status)->toBeNull()
        ->and($ballot->email_check_decision)->toBeNull();
    Bus::assertDispatchedTimes(SendVotingLinkJob::class, 1);
})->with([
    'a server error' => [fn() => Http::response('Server Error', 500)],
    'an error body (bad key or no credits)' => [
        fn() => Http::response(['error' => 'Invalid API Key or your account ran out of credits']),
    ],
    'a failed connection' => [fn() => Http::failedConnection()],
]);

it('does not call ZeroBounce when no API key is configured', function (): void {
    Bus::fake([SendVotingLinkJob::class]);
    $edition = votingOpenEdition();
    config(['services.zerobounce.api_key' => '']);
    Http::preventStrayRequests();
    Http::fake(['api-eu.zerobounce.net/v2/validate*' => Http::response(['status' => 'invalid'])]);

    postJson('/api/voting/request', ['email' => 'voter@example.test'])->assertStatus(200);

    Http::assertNothingSent();
    expect(Ballot::query()->forEdition($edition)->sole()->email_check_decision)->toBeNull();
    Bus::assertDispatchedTimes(SendVotingLinkJob::class, 1);
});

it('does not call ZeroBounce again for an address that already has a ballot', function (): void {
    Bus::fake([SendVotingLinkJob::class]);
    $edition = votingOpenEdition();
    fakeZeroBounceVerdict('valid');

    postJson('/api/voting/request', ['email' => 'voter@example.test'])->assertStatus(200);
    postJson('/api/voting/request', ['email' => 'voter@example.test'])->assertStatus(200);

    Http::assertSentCount(1);
    expect(Ballot::query()->forEdition($edition)->count())->toBe(1);
    Bus::assertDispatchedTimes(SendVotingLinkJob::class, 1);
});

it('sends ZeroBounce the address as typed and no IP', function (): void {
    Bus::fake([SendVotingLinkJob::class]);
    votingOpenEdition();
    fakeZeroBounceVerdict('valid');

    postJson('/api/voting/request', ['email' => 'Voter+News@Example.Test'])->assertStatus(200);

    Http::assertSent(fn($request): bool => $request['email'] === 'Voter+News@Example.Test'
        && $request['api_key'] === 'test-key'
        && $request['ip_address'] === '');
    Bus::assertDispatchedTimes(SendVotingLinkJob::class, 1);
});

it('treats the spellings of one mailbox as one voter', function (string $first, string $second): void {
    Bus::fake([SendVotingLinkJob::class]);
    $edition = votingOpenEdition();

    postJson('/api/voting/request', ['email' => $first])->assertStatus(200);
    postJson('/api/voting/request', ['email' => $second])->assertStatus(200);

    expect(Ballot::query()->forEdition($edition)->count())->toBe(1);
    Bus::assertDispatchedTimes(SendVotingLinkJob::class, 1);
})->with([
    'a +tag' => ['ana@example.test', 'ana+1@example.test'],
    'Gmail dots' => ['anapopescu@gmail.com', 'ana.popescu@gmail.com'],
    'googlemail.com' => ['anapopescu@gmail.com', 'anapopescu@googlemail.com'],
]);

it('keeps dotted addresses apart outside Gmail', function (): void {
    Bus::fake([SendVotingLinkJob::class]);
    $edition = votingOpenEdition();

    postJson('/api/voting/request', ['email' => 'anapopescu@example.test'])->assertStatus(200);
    postJson('/api/voting/request', ['email' => 'ana.popescu@example.test'])->assertStatus(200);

    expect(Ballot::query()->forEdition($edition)->count())->toBe(2);
    Bus::assertDispatchedTimes(SendVotingLinkJob::class, 2);
});
