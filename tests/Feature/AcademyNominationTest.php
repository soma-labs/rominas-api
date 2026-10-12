<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\Nomination\Enums\NominationStatus;
use Rominas\Academy\Nomination\Model\Nomination;
use Rominas\Catalog\Artist\Model\Artist;
use Rominas\Catalog\Band\Model\Band;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Catalog\NomineeSubmission\Enums\NomineeSubmissionStatus;
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
        ->assertJsonPath('data.can_submit', false)
        ->assertJsonPath('data.nominations_start_at', $edition->nominations_start_at->toJSON())
        ->assertJsonPath('data.nominations_end_at', $edition->nominations_end_at->toJSON())
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
        ->assertJsonPath('data.categories.0.has_nominees', true)
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

it('translates a validation error message when Accept-Language is ro', function (): void {
    $edition = openEdition(['status' => EditionStatus::Draft]);
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    actingMember();

    putJson(
        "/api/academy/nominations/categories/{$category->id}",
        ['nominees' => nomineeNames()],
        ['Accept-Language' => 'ro'],
    )
        ->assertStatus(422)
        ->assertJsonPath('errors.nominations.0', 'Nominalizările nu sunt deschise.')
        ->assertJsonPath('message', 'Nominalizările nu sunt deschise.');
});

it('keeps the English message when Accept-Language is absent or names an unsupported locale', function (?string $acceptLanguage): void {
    $edition = openEdition(['status' => EditionStatus::Draft]);
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    actingMember();

    $headers = $acceptLanguage === null ? [] : ['Accept-Language' => $acceptLanguage];

    putJson("/api/academy/nominations/categories/{$category->id}", ['nominees' => nomineeNames()], $headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.nominations.0', 'Nominations are not open.');
})->with([null, 'fr']);

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

it('submits a ballot that leaves other categories empty', function (): void {
    $edition = openEdition();
    $categoryA = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    $member = actingMember();

    putJson("/api/academy/nominations/categories/{$categoryA->id}", ['nominees' => nomineeNames()])->assertStatus(200);

    postJson('/api/academy/nominations/submit')
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'submitted');

    $nomination = Nomination::query()->forMember($member)->firstOrFail();
    expect($nomination->status)->toBe(NominationStatus::Submitted);
    expect($nomination->submitted_at)->not->toBeNull();
});

it('submits a ballot holding a single nominee', function (): void {
    $category = Category::factory()->for(openEdition())->create(['nominee_type' => NomineeType::Artist]);
    actingMember();

    putJson("/api/academy/nominations/categories/{$category->id}", ['nominees' => nomineeNames(1)])
        ->assertStatus(200)
        ->assertJsonPath('data.can_submit', true)
        ->assertJsonPath('data.categories.0.has_nominees', true);

    postJson('/api/academy/nominations/submit')
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'submitted');
});

it('fails submission when nothing has been saved', function (): void {
    Category::factory()->for(openEdition())->create(['nominee_type' => NomineeType::Artist]);
    actingMember();

    getJson('/api/academy/nominations')
        ->assertStatus(200)
        ->assertJsonPath('data.can_submit', false)
        ->assertJsonPath('data.categories.0.has_nominees', false);

    postJson('/api/academy/nominations/submit')
        ->assertStatus(422)
        ->assertJsonValidationErrors('nominations');
});

it('fails submission when the only saved category was cleared again', function (): void {
    $category = Category::factory()->for(openEdition())->create(['nominee_type' => NomineeType::Artist]);
    $member = actingMember();

    putJson("/api/academy/nominations/categories/{$category->id}", ['nominees' => nomineeNames()])->assertStatus(200);
    putJson("/api/academy/nominations/categories/{$category->id}", ['nominees' => []])
        ->assertStatus(200)
        ->assertJsonPath('data.can_submit', false);

    postJson('/api/academy/nominations/submit')
        ->assertStatus(422)
        ->assertJsonValidationErrors('nominations');

    expect(Nomination::query()->forMember($member)->firstOrFail()->status)->toBe(NominationStatus::Draft);
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
        ->assertJsonPath('data.categories.0.has_nominees', true)
        ->assertJsonCount(5, 'data.categories.0.rankings');
});

it('suggests catalog entries of the category type whose name contains the typed text, names starting with it first', function (): void {
    $category = Category::factory()->for(openEdition())->create(['nominee_type' => NomineeType::Artist]);
    $delia = Artist::factory()->create(['name' => 'Delia']);
    $adelina = Artist::factory()->create(['name' => 'Adelina']);
    Artist::factory()->create(['name' => 'Smiley']);
    Band::factory()->create(['name' => 'Delirium']);
    actingMember();

    getJson("/api/academy/nominations/categories/{$category->id}/suggestions?search=del")
        ->assertStatus(200)
        ->assertExactJson(['data' => [
            ['id' => $delia->id, 'name' => 'Delia'],
            ['id' => $adelina->id, 'name' => 'Adelina'],
        ]]);
});

it('returns at most eight suggestions', function (): void {
    $category = Category::factory()->for(openEdition())->create(['nominee_type' => NomineeType::Artist]);
    foreach (range(1, 9) as $number) {
        Artist::factory()->create(['name' => "Orchestra {$number}"]);
    }
    actingMember();

    getJson("/api/academy/nominations/categories/{$category->id}/suggestions?search=orchestra")
        ->assertStatus(200)
        ->assertJsonCount(8, 'data');
});

it('matches the typed text literally, not as a wildcard', function (): void {
    $category = Category::factory()->for(openEdition())->create(['nominee_type' => NomineeType::Artist]);
    Artist::factory()->create(['name' => 'Delia']);
    actingMember();

    getJson("/api/academy/nominations/categories/{$category->id}/suggestions?search=" . urlencode('d%a'))
        ->assertStatus(200)
        ->assertExactJson(['data' => []]);
});

it('rejects a suggestion search shorter than three characters', function (): void {
    $category = Category::factory()->for(openEdition())->create(['nominee_type' => NomineeType::Artist]);
    actingMember();

    getJson("/api/academy/nominations/categories/{$category->id}/suggestions?search=de")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['search' => 'The search field must be at least 3 characters.']);
});

it('requires member authentication for suggestions', function (): void {
    $category = Category::factory()->for(openEdition())->create(['nominee_type' => NomineeType::Artist]);

    getJson("/api/academy/nominations/categories/{$category->id}/suggestions?search=del")->assertStatus(401);
});

it('links a pick chosen from the suggestions to its catalog entry without staging a submission', function (): void {
    $edition = openEdition();
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    $delia = Artist::factory()->create(['name' => 'Delia']);
    $member = actingMember();

    putJson("/api/academy/nominations/categories/{$category->id}", [
        'nominees' => [
            ['name' => 'Delia', 'nominee_id' => $delia->id],
            ['name' => 'A Brand New Act', 'nominee_id' => null],
        ],
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.categories.0.rankings.0.raw_name', null)
        ->assertJsonPath('data.categories.0.rankings.0.submission_status', null)
        ->assertJsonPath('data.categories.0.rankings.0.nominee_id', $delia->id)
        ->assertJsonPath('data.categories.0.rankings.0.nominee.name', 'Delia')
        ->assertJsonPath('data.categories.0.rankings.1.raw_name', 'A Brand New Act')
        ->assertJsonPath('data.categories.0.rankings.1.submission_status', 'pending')
        ->assertJsonPath('data.categories.0.rankings.1.nominee_id', null);

    // Only the typed name awaits reconciliation.
    expect(NomineeSubmission::query()->where('edition_id', $edition->id)->pluck('raw_name')->all())
        ->toBe(['A Brand New Act']);

    $chosen = Nomination::query()->forMember($member)->firstOrFail()->rankings()->where('rank', 1)->firstOrFail();
    expect($chosen->nominee_submission_id)->toBeNull();
    expect($chosen->nominee_id)->toBe($delia->id);
});

it('counts picks chosen from the suggestions towards a submittable ballot', function (): void {
    $category = Category::factory()->for(openEdition())->create(['nominee_type' => NomineeType::Artist]);
    $picks = Artist::factory()->count(5)->create()
        ->map(fn(Artist $artist): array => ['name' => $artist->name, 'nominee_id' => $artist->id])
        ->all();
    actingMember();

    putJson("/api/academy/nominations/categories/{$category->id}", ['nominees' => $picks])
        ->assertStatus(200)
        ->assertJsonPath('data.categories.0.has_nominees', true);

    postJson('/api/academy/nominations/submit')
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'submitted');
});

it('rejects a chosen catalog entry that does not exist for the category type', function (): void {
    $category = Category::factory()->for(openEdition())->create(['nominee_type' => NomineeType::Artist]);
    $band = Band::factory()->create(['name' => 'Delirium']);
    $member = actingMember();

    putJson("/api/academy/nominations/categories/{$category->id}", [
        'nominees' => [['name' => 'Delirium', 'nominee_id' => $band->id]],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'nominees.0.nominee_id' => 'The selected nominee no longer exists. Please choose again.',
        ]);

    expect(Nomination::query()->forMember($member)->count())->toBe(0);
});

it('rejects the same catalog entry chosen twice', function (): void {
    $category = Category::factory()->for(openEdition())->create(['nominee_type' => NomineeType::Artist]);
    $delia = Artist::factory()->create(['name' => 'Delia']);
    actingMember();

    putJson("/api/academy/nominations/categories/{$category->id}", [
        'nominees' => [
            ['name' => 'Delia', 'nominee_id' => $delia->id],
            ['name' => 'Delia', 'nominee_id' => $delia->id],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['nominees' => 'Two of your picks refer to the same nominee.']);
});

it('rejects a typed name that is the name of a chosen catalog entry', function (): void {
    $edition = openEdition();
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    $delia = Artist::factory()->create(['name' => 'Delia']);
    actingMember();

    putJson("/api/academy/nominations/categories/{$category->id}", [
        'nominees' => [
            ['name' => 'delia!', 'nominee_id' => null],
            ['name' => 'Delia', 'nominee_id' => $delia->id],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['nominees' => 'Two of your picks refer to the same nominee.']);

    // The refused save staged nothing.
    expect(NomineeSubmission::query()->where('edition_id', $edition->id)->count())->toBe(0);
});

it('rejects a typed name already reconciled to a chosen catalog entry', function (): void {
    $edition = openEdition();
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    $delia = Artist::factory()->create(['name' => 'Delia']);
    NomineeSubmission::factory()->create([
        'edition_id' => $edition->id,
        'nominee_type' => NomineeType::Artist,
        'raw_name' => 'Delia Matache',
        'normalized_name' => 'delia-matache',
        'status' => NomineeSubmissionStatus::Resolved,
        'resolved_nominee_id' => $delia->id,
    ]);
    actingMember();

    putJson("/api/academy/nominations/categories/{$category->id}", [
        'nominees' => [
            ['name' => 'Delia', 'nominee_id' => $delia->id],
            ['name' => 'Delia Matache', 'nominee_id' => null],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['nominees' => 'Two of your picks refer to the same nominee.']);
});

it('accepts two chosen catalog entries that share a name', function (): void {
    $category = Category::factory()->for(openEdition())->create(['nominee_type' => NomineeType::Artist]);
    $first = Artist::factory()->create(['name' => 'Aurora', 'slug' => 'aurora']);
    $second = Artist::factory()->create(['name' => 'Aurora', 'slug' => 'aurora-2']);
    actingMember();

    putJson("/api/academy/nominations/categories/{$category->id}", [
        'nominees' => [
            ['name' => 'Aurora', 'nominee_id' => $first->id],
            ['name' => 'Aurora', 'nominee_id' => $second->id],
        ],
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.categories.0.rankings.0.nominee_id', $first->id)
        ->assertJsonPath('data.categories.0.rankings.1.nominee_id', $second->id);
});

it('leaves the categories that are not ranked off the ballot', function (): void {
    $edition = openEdition();
    $ranked = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    Category::factory()->for($edition)->proposeOrVote()->create();
    Category::factory()->for($edition)->appointed()->create();
    actingMember();

    getJson('/api/academy/nominations')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data.categories')
        ->assertJsonPath('data.categories.0.id', $ranked->id);
});

it('does not accept a ranking for a category that is not ranked', function (): void {
    $category = Category::factory()->for(openEdition())->proposeOrVote()->create();
    actingMember();

    putJson("/api/academy/nominations/categories/{$category->id}", ['nominees' => [['name' => 'Someone']]])
        ->assertStatus(404);

    expect(Nomination::query()->count())->toBe(0);
});

it('offers no catalog suggestions for a category that is not ranked', function (): void {
    $category = Category::factory()->for(openEdition())->proposeOrVote()->create();
    actingMember();

    getJson("/api/academy/nominations/categories/{$category->id}/suggestions?search=some")
        ->assertStatus(404);
});
