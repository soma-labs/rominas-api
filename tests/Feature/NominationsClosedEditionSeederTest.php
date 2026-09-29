<?php

declare(strict_types=1);

use Database\Seeders\NominationsClosedEditionSeeder;
use Rominas\Academy\Nomination\Enums\NominationStatus;
use Rominas\Academy\Nomination\Model\Nomination;
use Rominas\Academy\Nomination\Model\NominationRanking;
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
        ->and($categoryCount)->toBe(6);

    $nominations = Nomination::query()->where('edition_id', $edition->id)->with('rankings')->get();

    expect($nominations)->toHaveCount(20);

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

it('reuses the active edition and its categories, closing nominations', function (): void {
    $edition = Edition::factory()->status(EditionStatus::NominationsOpen)->create();
    $category = Category::factory()->create(['edition_id' => $edition->id]);

    seed(NominationsClosedEditionSeeder::class);

    expect(Edition::query()->active()->sole()->is($edition))->toBeTrue()
        ->and($edition->fresh()->status)->toBe(EditionStatus::NominationsClosed)
        ->and(Category::query()->filterByEditionId($edition->id)->pluck('id')->all())->toBe([$category->id])
        ->and(NominationRanking::query()->where('category_id', $category->id)->count())->toBe(20 * 5);
});

it('keeps the status of an active edition already past nominations_closed', function (): void {
    $edition = Edition::factory()->status(EditionStatus::VotingOpen)->create();

    seed(NominationsClosedEditionSeeder::class);

    expect($edition->fresh()->status)->toBe(EditionStatus::VotingOpen)
        ->and(Nomination::query()->where('edition_id', $edition->id)->count())->toBe(20);
});
