<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Rominas\Academy\Nomination\Model\Nomination;
use Rominas\Academy\Nomination\Model\NominationRanking;
use Rominas\Academy\Shortlist\Model\ShortlistEntry;
use Rominas\Catalog\Artist\Model\Artist;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
use Rominas\Users\Model\User;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

/**
 * Seed one submitted (or draft) ballot ranking the given artists in order (index 0 → rank 1) for a
 * category. Only submitted ballots should feed the shortlist.
 *
 * @param  list<int>  $artistIdsByRank
 */
function seedShortlistBallot(Edition $edition, Category $category, array $artistIdsByRank, bool $submitted = true): void
{
    $factory = Nomination::factory();
    $nomination = ($submitted ? $factory->submitted() : $factory)->create(['edition_id' => $edition->id]);

    foreach ($artistIdsByRank as $index => $artistId) {
        NominationRanking::factory()->create([
            'nomination_id' => $nomination->id,
            'category_id' => $category->id,
            'rank' => $index + 1,
            'nominee_type' => NomineeType::Artist,
            'nominee_id' => $artistId,
        ]);
    }
}

/**
 * @return array{Edition, Category}
 */
function shortlistEditionAndCategory(EditionStatus $status = EditionStatus::NominationsClosed): array
{
    $edition = Edition::factory()->status($status)->create();
    $category = Category::factory()->create([
        'edition_id' => $edition->id,
        'nominee_type' => NomineeType::Artist,
    ]);

    return [$edition, $category];
}

it('generates the top 5 nominees by summed points, excluding draft ballots', function (): void {
    actingAsSuperAdmin();
    [$edition, $category] = shortlistEditionAndCategory();

    // Six artists, created in order so E has a lower id than F (tie-break check).
    [$a, $b, $c, $d, $e, $f] = Artist::factory()->count(6)->create()->all();

    // Two submitted ballots: A=20, B=16, C=12, D=8, then E and F tie at 2 (E wins on lower id).
    seedShortlistBallot($edition, $category, [$a->id, $b->id, $c->id, $d->id, $e->id]);
    seedShortlistBallot($edition, $category, [$a->id, $b->id, $c->id, $d->id, $f->id]);
    // A draft ballot that, if wrongly counted, would push F ahead of E — must be ignored.
    seedShortlistBallot($edition, $category, [$f->id], submitted: false);

    postJson("/api/admin/editions/{$edition->id}/categories/{$category->id}/shortlist")
        ->assertStatus(200)
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('data.0.nominee_id', $a->id)
        ->assertJsonPath('data.0.points', 20)
        ->assertJsonPath('data.0.position', 1)
        ->assertJsonPath('data.4.nominee_id', $e->id)
        ->assertJsonPath('data.4.points', 2)
        ->assertJsonPath('data.4.position', 5);

    expect(ShortlistEntry::query()->forEdition($edition)->forCategory($category)->count())->toBe(5);
    expect(ShortlistEntry::query()->where('nominee_id', $f->id)->exists())->toBeFalse();
});

it('produces a shorter shortlist when fewer than 5 distinct nominees exist', function (): void {
    actingAsSuperAdmin();
    [$edition, $category] = shortlistEditionAndCategory();

    [$a, $b, $c] = Artist::factory()->count(3)->create()->all();
    seedShortlistBallot($edition, $category, [$a->id, $b->id, $c->id]);

    postJson("/api/admin/editions/{$edition->id}/categories/{$category->id}/shortlist")
        ->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

it('replaces the existing entries when a category is regenerated', function (): void {
    actingAsSuperAdmin();
    [$edition, $category] = shortlistEditionAndCategory();

    [$a, $b, $c] = Artist::factory()->count(3)->create()->all();
    seedShortlistBallot($edition, $category, [$a->id, $b->id, $c->id]);

    postJson("/api/admin/editions/{$edition->id}/categories/{$category->id}/shortlist")->assertStatus(200);
    postJson("/api/admin/editions/{$edition->id}/categories/{$category->id}/shortlist")
        ->assertStatus(200)
        ->assertJsonCount(3, 'data');

    // Regeneration overwrites rather than appends.
    expect(ShortlistEntry::query()->forEdition($edition)->forCategory($category)->count())->toBe(3);
});

it('refuses generation once the edition has left nominations_closed', function (): void {
    actingAsSuperAdmin();
    [$edition, $category] = shortlistEditionAndCategory(EditionStatus::VotingOpen);

    $a = Artist::factory()->create();
    seedShortlistBallot($edition, $category, [$a->id]);

    postJson("/api/admin/editions/{$edition->id}/categories/{$category->id}/shortlist")
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('status');

    expect(ShortlistEntry::query()->forEdition($edition)->count())->toBe(0);
});

it('bulk-generates a shortlist for every category in the edition', function (): void {
    actingAsSuperAdmin();
    $edition = Edition::factory()->status(EditionStatus::NominationsClosed)->create();
    $first = Category::factory()->create(['edition_id' => $edition->id, 'nominee_type' => NomineeType::Artist]);
    $second = Category::factory()->create(['edition_id' => $edition->id, 'nominee_type' => NomineeType::Artist]);

    [$a, $b] = Artist::factory()->count(2)->create()->all();
    seedShortlistBallot($edition, $first, [$a->id, $b->id]);
    seedShortlistBallot($edition, $second, [$b->id, $a->id]);

    postJson("/api/admin/editions/{$edition->id}/shortlist")
        ->assertStatus(200)
        ->assertJsonCount(4, 'data');

    expect(ShortlistEntry::query()->forEdition($edition)->forCategory($first)->count())->toBe(2);
    expect(ShortlistEntry::query()->forEdition($edition)->forCategory($second)->count())->toBe(2);
});

it('lists an edition\'s generated shortlist for review', function (): void {
    actingAsSuperAdmin();
    [$edition, $category] = shortlistEditionAndCategory();

    [$a, $b] = Artist::factory()->count(2)->create()->all();
    seedShortlistBallot($edition, $category, [$a->id, $b->id]);
    postJson("/api/admin/editions/{$edition->id}/categories/{$category->id}/shortlist")->assertStatus(200);

    getJson("/api/admin/editions/{$edition->id}/shortlist")
        ->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.position', 1);
});

it('lists ranked shortlist candidates for a category', function (): void {
    actingAsSuperAdmin();
    [$edition, $category] = shortlistEditionAndCategory();

    [$a, $b, $c] = Artist::factory()->count(3)->create()->all();
    seedShortlistBallot($edition, $category, [$a->id, $b->id, $c->id]); // A=10 B=8 C=6

    getJson("/api/admin/editions/{$edition->id}/categories/{$category->id}/shortlist/candidates")
        ->assertStatus(200)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.nominee_id', $a->id)
        ->assertJsonPath('data.0.points', 10)
        ->assertJsonPath('data.0.rank', 1)
        ->assertJsonPath('data.2.nominee_id', $c->id);
});

it('replaces a category shortlist with the admin\'s final ordered nominees', function (): void {
    actingAsSuperAdmin();
    [$edition, $category] = shortlistEditionAndCategory();

    [$a, $b, $c, $d] = Artist::factory()->count(4)->create()->all();
    seedShortlistBallot($edition, $category, [$a->id, $b->id, $c->id, $d->id]); // A=10 B=8 C=6 D=4
    postJson("/api/admin/editions/{$edition->id}/categories/{$category->id}/shortlist")->assertStatus(200);

    // Admin trims + reorders to a final top-3: D, A, B.
    putJson("/api/admin/editions/{$edition->id}/categories/{$category->id}/shortlist", [
        'nominees' => [$d->id, $a->id, $b->id],
    ])
        ->assertStatus(200)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.nominee_id', $d->id)
        ->assertJsonPath('data.0.position', 1)
        ->assertJsonPath('data.2.nominee_id', $b->id);

    expect(ShortlistEntry::query()->forEdition($edition)->forCategory($category)->count())->toBe(3);
    // Points are re-derived from the academy tally (A was ranked 1 → 10 points).
    expect(ShortlistEntry::query()->where('nominee_id', $a->id)->value('points'))->toBe(10);
});

it('rejects a shortlist larger than the max size', function (): void {
    actingAsSuperAdmin();
    [$edition, $category] = shortlistEditionAndCategory();

    $ids = Artist::factory()->count(6)->create()->pluck('id')->all();

    putJson("/api/admin/editions/{$edition->id}/categories/{$category->id}/shortlist", ['nominees' => $ids])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('nominees');
});

it('rejects a nominee that does not exist for the category', function (): void {
    actingAsSuperAdmin();
    [$edition, $category] = shortlistEditionAndCategory();

    $a = Artist::factory()->create();

    putJson("/api/admin/editions/{$edition->id}/categories/{$category->id}/shortlist", [
        'nominees' => [$a->id, 999999],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('nominees');
});

it('refuses to adjust once the edition has left nominations_closed', function (): void {
    actingAsSuperAdmin();
    [$edition, $category] = shortlistEditionAndCategory(EditionStatus::VotingOpen);

    $a = Artist::factory()->create();

    putJson("/api/admin/editions/{$edition->id}/categories/{$category->id}/shortlist", ['nominees' => [$a->id]])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('status');
});

it('forbids a user without the shortlists permission', function (): void {
    Sanctum::actingAs(User::factory()->create());
    [$edition, $category] = shortlistEditionAndCategory();

    postJson("/api/admin/editions/{$edition->id}/categories/{$category->id}/shortlist")->assertStatus(403);
    getJson("/api/admin/editions/{$edition->id}/shortlist")->assertStatus(403);
});
