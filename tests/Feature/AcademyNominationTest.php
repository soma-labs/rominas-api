<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\Nomination\Enums\NominationStatus;
use Rominas\Academy\Nomination\Model\Nomination;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
use Rominas\Users\Model\User;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

/**
 * An edition whose nomination window is open now: status nominations_open + clock inside the window.
 *
 * @param  array<string, mixed>  $overrides
 */
function openEdition(array $overrides = []): Edition
{
    return Edition::factory()->create(array_merge([
        'status' => EditionStatus::NominationsOpen,
        'nominations_start_at' => now()->subDay(),
        'nominations_end_at' => now()->addWeek(),
    ], $overrides));
}

function actingMember(): Member
{
    $member = Member::factory()->active()->create();
    Sanctum::actingAs($member, ['member'], 'member');

    return $member;
}

/**
 * Distinct free-text nominee names, as a member would type them.
 *
 * @return list<string>
 */
function nomineeNames(int $count = 5): array
{
    return collect(range(1, $count))
        ->map(fn(int $n): string => "Nominee {$n}")
        ->all();
}

it('returns the ballot with every category and no persisted row when resuming', function (): void {
    $edition = openEdition();
    Category::factory()->count(2)->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    actingMember();

    getJson('/api/academy/nominations')
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.is_complete', false)
        ->assertJsonCount(2, 'data.categories');

    expect(Nomination::query()->count())->toBe(0);
});

it('saves a typed-in category ranking, staging each name as a pending submission', function (): void {
    $edition = openEdition();
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    $names = nomineeNames();
    $member = actingMember();

    putJson("/api/academy/nominations/categories/{$category->id}", ['nominees' => $names])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.categories.0.complete', true)
        ->assertJsonPath('data.categories.0.rankings.0.raw_name', $names[0])
        ->assertJsonPath('data.categories.0.rankings.0.submission_status', 'pending')
        ->assertJsonPath('data.categories.0.rankings.0.nominee_id', null)
        ->assertJsonPath('data.categories.0.rankings.0.nominee', null);

    // One pending submission per typed name, deduplicated per edition/type.
    expect(NomineeSubmission::query()->where('edition_id', $edition->id)->count())->toBe(5);

    $nomination = Nomination::query()->forMember($member)->firstOrFail();
    $ordered = $nomination->rankings()->orderBy('rank')->with('nomineeSubmission')->get()
        ->map(fn($ranking): string => $ranking->nomineeSubmission->raw_name)->all();
    expect($ordered)->toBe($names);
});

it('replaces and reorders a category ranking on re-save', function (): void {
    $edition = openEdition();
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    $names = nomineeNames();
    $member = actingMember();

    putJson("/api/academy/nominations/categories/{$category->id}", ['nominees' => $names])->assertStatus(200);
    putJson("/api/academy/nominations/categories/{$category->id}", ['nominees' => array_reverse($names)])->assertStatus(200);

    $nomination = Nomination::query()->forMember($member)->firstOrFail();
    expect($nomination->rankings()->count())->toBe(5);
    $ordered = $nomination->rankings()->orderBy('rank')->with('nomineeSubmission')->get()
        ->map(fn($ranking): string => $ranking->nomineeSubmission->raw_name)->all();
    expect($ordered)->toBe(array_reverse($names));

    // Re-save reuses the same deduplicated submissions rather than creating new ones.
    expect(NomineeSubmission::query()->where('edition_id', $edition->id)->count())->toBe(5);
});

it('accepts free-text nominees that are not in the catalog', function (): void {
    $edition = openEdition();
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    actingMember();

    putJson("/api/academy/nominations/categories/{$category->id}", ['nominees' => ['A Brand New Act']])
        ->assertStatus(200)
        ->assertJsonPath('data.categories.0.rankings.0.raw_name', 'A Brand New Act');

    expect(NomineeSubmission::query()->where('normalized_name', 'a-brand-new-act')->exists())->toBeTrue();
});

it('rejects two picks that normalize to the same nominee', function (): void {
    $edition = openEdition();
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    actingMember();

    putJson("/api/academy/nominations/categories/{$category->id}", ['nominees' => ['Taylor Swift', 'taylor  swift']])
        ->assertStatus(422);
});

it('rejects an exact duplicate nominee', function (): void {
    $edition = openEdition();
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    actingMember();

    putJson("/api/academy/nominations/categories/{$category->id}", ['nominees' => ['Same', 'Same']])
        ->assertStatus(422);
});

it('rejects more than five nominees', function (): void {
    $edition = openEdition();
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    actingMember();

    putJson("/api/academy/nominations/categories/{$category->id}", ['nominees' => nomineeNames(6)])
        ->assertStatus(422);
});

it('rejects a category that does not belong to the open edition', function (): void {
    openEdition();
    $otherEdition = Edition::factory()->archived()->create();
    $category = Category::factory()->for($otherEdition)->create(['nominee_type' => NomineeType::Artist]);
    actingMember();

    putJson("/api/academy/nominations/categories/{$category->id}", ['nominees' => nomineeNames()])
        ->assertStatus(422);
});

it('rejects saving when the edition status is not nominations_open', function (): void {
    $edition = openEdition(['status' => EditionStatus::Draft]);
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    actingMember();

    putJson("/api/academy/nominations/categories/{$category->id}", ['nominees' => nomineeNames()])
        ->assertStatus(422);
});

it('rejects saving when the clock is outside the nomination window', function (): void {
    $edition = openEdition([
        'nominations_start_at' => now()->addDay(),
        'nominations_end_at' => now()->addWeek(),
    ]);
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    actingMember();

    putJson("/api/academy/nominations/categories/{$category->id}", ['nominees' => nomineeNames()])
        ->assertStatus(422);
});

it('fails submission when a category is incomplete', function (): void {
    $edition = openEdition();
    $categoryA = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    $member = actingMember();

    putJson("/api/academy/nominations/categories/{$categoryA->id}", ['nominees' => nomineeNames()])->assertStatus(200);

    postJson('/api/academy/nominations/submit')->assertStatus(422);

    expect(Nomination::query()->forMember($member)->firstOrFail()->status)->toBe(NominationStatus::Draft);
});

it('submits when every category has exactly five nominees', function (): void {
    $edition = openEdition();
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    $member = actingMember();

    putJson("/api/academy/nominations/categories/{$category->id}", ['nominees' => nomineeNames()])->assertStatus(200);

    postJson('/api/academy/nominations/submit')
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.is_complete', true);

    $nomination = Nomination::query()->forMember($member)->firstOrFail();
    expect($nomination->status)->toBe(NominationStatus::Submitted);
    expect($nomination->submitted_at)->not->toBeNull();
});

it('locks the ballot after submission', function (): void {
    $edition = openEdition();
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    $names = nomineeNames();
    actingMember();

    putJson("/api/academy/nominations/categories/{$category->id}", ['nominees' => $names])->assertStatus(200);
    postJson('/api/academy/nominations/submit')->assertStatus(200);

    putJson("/api/academy/nominations/categories/{$category->id}", ['nominees' => $names])->assertStatus(422);
    postJson('/api/academy/nominations/submit')->assertStatus(422);
});

it('keeps a single ballot per member and edition across saves', function (): void {
    $edition = openEdition();
    $categoryA = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    $categoryB = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    $member = actingMember();

    putJson("/api/academy/nominations/categories/{$categoryA->id}", ['nominees' => nomineeNames()])->assertStatus(200);
    putJson("/api/academy/nominations/categories/{$categoryB->id}", ['nominees' => nomineeNames()])->assertStatus(200);

    expect(Nomination::query()->forMember($member)->count())->toBe(1);
});

it('requires member authentication', function (): void {
    getJson('/api/academy/nominations')->assertStatus(401);
});

it('rejects an admin User token on the member nomination routes', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('admin')->plainTextToken;

    getJson('/api/academy/nominations', ['Authorization' => 'Bearer ' . $token])
        ->assertStatus(401);
});

it('resumes a saved draft after re-authenticating', function (): void {
    $edition = openEdition();
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    $member = Member::factory()->active()->create();
    Sanctum::actingAs($member, ['member'], 'member');

    putJson("/api/academy/nominations/categories/{$category->id}", ['nominees' => nomineeNames()])->assertStatus(200);

    // Simulate a fresh request/session for the same member.
    Sanctum::actingAs($member, ['member'], 'member');

    getJson('/api/academy/nominations')
        ->assertStatus(200)
        ->assertJsonPath('data.categories.0.complete', true)
        ->assertJsonCount(5, 'data.categories.0.rankings');
});
