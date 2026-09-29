<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
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
