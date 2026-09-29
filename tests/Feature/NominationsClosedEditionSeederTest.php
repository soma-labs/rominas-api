<?php

declare(strict_types=1);

use Database\Seeders\NominationsClosedEditionSeeder;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\Nomination\Enums\NominationStatus;
use Rominas\Academy\Nomination\Model\Nomination;
use Rominas\Academy\Nomination\Model\NominationRanking;
use Rominas\Catalog\Artist\Model\Artist;
use Rominas\Catalog\NomineeSubmission\Enums\NomineeSubmissionStatus;
use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;

use function Pest\Laravel\seed;

it('creates a nominations_closed edition with fully submitted, unreconciled nominations', function (): void {
    seed(NominationsClosedEditionSeeder::class);

    $edition = Edition::query()->active()->sole();
    $categoryCount = Category::query()->filterByEditionId($edition->id)->count();

    expect($edition->status)->toBe(EditionStatus::NominationsClosed)
        ->and($categoryCount)->toBeGreaterThan(0);

    $nominations = Nomination::query()->where('edition_id', $edition->id)->with('rankings')->get();

    expect($nominations)->not->toBeEmpty()
        ->and($nominations)->toHaveCount(Member::query()->count());

    foreach ($nominations as $nomination) {
        expect($nomination->status)->toBe(NominationStatus::Submitted)
            ->and($nomination->submitted_at)->not->toBeNull()
            ->and($nomination->rankings->countBy('category_id')->all())
            ->toEqual(array_fill_keys(Category::query()->filterByEditionId($edition->id)->pluck('id')->all(), 5));
    }

    expect(NomineeSubmission::query()->where('status', '!=', NomineeSubmissionStatus::Pending)->exists())->toBeFalse()
        ->and(NominationRanking::query()->whereNotNull('nominee_id')->exists())->toBeFalse()
        ->and(NominationRanking::query()->whereNull('nominee_submission_id')->exists())->toBeFalse();
});

it('wipes existing academy and catalog data but keeps and reuses the active edition', function (): void {
    $archived = Edition::factory()->archived()->create();
    $archivedCategory = Category::factory()->create(['edition_id' => $archived->id]);
    $edition = Edition::factory()->status(EditionStatus::NominationsOpen)->create();
    $staleCategory = Category::factory()->create(['edition_id' => $edition->id]);
    $staleMember = Member::factory()->create();
    $staleArtist = Artist::factory()->create();
    $staleSubmission = NomineeSubmission::factory()->create(['edition_id' => $edition->id]);
    Nomination::factory()->create(['member_id' => $staleMember->id, 'edition_id' => $edition->id]);

    seed(NominationsClosedEditionSeeder::class);

    expect(Edition::query()->active()->sole()->is($edition))->toBeTrue()
        ->and($edition->fresh()->status)->toBe(EditionStatus::NominationsClosed)
        ->and($archived->fresh())->not->toBeNull()
        ->and(Category::query()->whereKey([$archivedCategory->id, $staleCategory->id])->exists())->toBeFalse()
        ->and(Category::query()->filterByEditionId($edition->id)->exists())->toBeTrue()
        ->and(Member::query()->whereKey($staleMember->id)->exists())->toBeFalse()
                ->and(Artist::query()->whereKey($staleArtist->id)->exists())->toBeFalse()
        ->and(NomineeSubmission::query()->whereKey($staleSubmission->id)->exists())->toBeFalse()
        ->and(Nomination::query()->count())->toBe(Member::query()->count());
});

it('keeps the status of an active edition already past nominations_closed', function (): void {
    $edition = Edition::factory()->status(EditionStatus::VotingOpen)->create();

    seed(NominationsClosedEditionSeeder::class);

    expect($edition->fresh()->status)->toBe(EditionStatus::VotingOpen)
        ->and(Nomination::query()->where('edition_id', $edition->id)->count())->toBe(Member::query()->count());
});
