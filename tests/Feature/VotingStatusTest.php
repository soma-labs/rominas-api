<?php

declare(strict_types=1);

use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;

use function Pest\Laravel\freezeTime;
use function Pest\Laravel\getJson;

/**
 * An active edition in the given status whose voting window runs from 1 day ago to 1 week from now,
 * shifted by the given offsets (in days) so a test can put "now" before, inside or after it.
 */
function statusEdition(EditionStatus $status, int $startOffsetDays = -1, int $endOffsetDays = 7): Edition
{
    $now = now();

    return Edition::factory()->status($status)->create([
        'name' => 'Rominas 2026',
        'starts_at' => $now->copy()->subMonths(2),
        'nominations_start_at' => $now->copy()->subMonths(2)->addDay(),
        'nominations_end_at' => $now->copy()->addDays($startOffsetDays - 1),
        'voting_start_at' => $now->copy()->addDays($startOffsetDays),
        'voting_end_at' => $now->copy()->addDays($endOffsetDays),
        'ends_at' => $now->copy()->addMonths(2),
    ]);
}

it('reports none with null edition fields when there is no active edition', function (): void {
    Edition::factory()->archived()->create();

    getJson('/api/voting/status')
        ->assertStatus(200)
        ->assertExactJson(['data' => [
            'state' => 'none',
            'edition' => null,
            'voting_start_at' => null,
            'voting_end_at' => null,
        ]]);
});

it('reports open with the edition name and window when voting is open', function (): void {
    freezeTime();
    $edition = statusEdition(EditionStatus::VotingOpen);

    getJson('/api/voting/status')
        ->assertStatus(200)
        ->assertJsonPath('data.state', 'open')
        ->assertJsonPath('data.edition.name', 'Rominas 2026')
        ->assertJsonPath('data.voting_start_at', $edition->voting_start_at->toJSON())
        ->assertJsonPath('data.voting_end_at', $edition->voting_end_at->toJSON());
});

it('reports the state derived from the edition status and window', function (EditionStatus $status, int $startOffsetDays, int $endOffsetDays, string $state): void {
    statusEdition($status, $startOffsetDays, $endOffsetDays);

    getJson('/api/voting/status')
        ->assertStatus(200)
        ->assertJsonPath('data.state', $state);
})->with([
    'draft' => [EditionStatus::Draft, 10, 20, 'upcoming'],
    'nominations closed' => [EditionStatus::NominationsClosed, 10, 20, 'upcoming'],
    'voting_open before the start date' => [EditionStatus::VotingOpen, 1, 7, 'upcoming'],
    'voting_open after the end date' => [EditionStatus::VotingOpen, -7, -1, 'closed'],
    'voting closed' => [EditionStatus::VotingClosed, -1, 7, 'closed'],
    'results published' => [EditionStatus::ResultsPublished, -7, -1, 'closed'],
]);
