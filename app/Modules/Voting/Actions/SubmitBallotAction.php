<?php

declare(strict_types=1);

namespace Rominas\Voting\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Rominas\Academy\Shortlist\Model\ShortlistEntry;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Model\Edition;
use Rominas\Scoring\PublicRankPoints;
use Rominas\Voting\DataTransferObjects\BallotSubmissionData;
use Rominas\Voting\DataTransferObjects\CategoryVoteData;
use Rominas\Voting\Enums\BallotStatus;
use Rominas\Voting\Model\Ballot;
use Rominas\Voting\Model\BallotRanking;
use Rominas\Voting\Support\VoterHasher;

/**
 * Casts a public voter's one-shot ballot. Resolves the link token (must be an unused ballot for the open
 * voting edition), then validates every included category: it must belong to the edition and rank exactly
 * {@see PublicRankPoints::RANKS} (3) distinct nominees drawn from that category's shortlist, in order of
 * preference (PHAZE 4) — or all of them if the shortlist has fewer than 3. At least one category must be
 * voted.
 *
 * On success the ranks are persisted and the ballot is marked `submitted` (terminal, single-use) with the
 * submitter's hashed IP, all in one transaction. Points are not computed here — Scoring derives them later.
 */
class SubmitBallotAction
{
    public function __construct(
        private readonly ResolveBallotByTokenAction $resolveBallot,
    ) {}

    /**
     * @return Ballot the submitted ballot
     */
    public function execute(BallotSubmissionData $data): Ballot
    {
        $ballot = $this->resolveBallot->execute($data->token);

        /** @var Edition $edition */
        $edition = $ballot->edition;

        if ($data->categories === []) {
            throw ValidationException::withMessages([
                'categories' => 'Selectează cel puțin o categorie pentru a vota.',
            ]);
        }

        /** @var list<array{category: Category, entriesByNomineeId: array<int, ShortlistEntry>, nomineeIds: list<int>}> $prepared */
        $prepared = [];

        foreach ($data->categories as $vote) {
            $prepared[] = $this->validateCategoryVote($edition, $vote);
        }

        return DB::transaction(function () use ($ballot, $prepared, $data): Ballot {
            foreach ($prepared as $item) {
                $rank = 1;

                foreach ($item['nomineeIds'] as $nomineeId) {
                    $entry = $item['entriesByNomineeId'][$nomineeId];

                    BallotRanking::query()->create([
                        'ballot_id' => $ballot->id,
                        'category_id' => $item['category']->id,
                        'rank' => $rank,
                        'nominee_type' => $entry->nominee_type,
                        'nominee_id' => $nomineeId,
                    ]);

                    $rank++;
                }
            }

            $ballot->status = BallotStatus::Submitted;
            $ballot->submitted_at = now();
            $ballot->ip_hash = VoterHasher::ipHash($data->ip);
            $ballot->save();

            return $ballot;
        });
    }

    /**
     * @return array{category: Category, entriesByNomineeId: array<int, ShortlistEntry>, nomineeIds: list<int>}
     */
    private function validateCategoryVote(Edition $edition, CategoryVoteData $vote): array
    {
        $category = Category::query()
            ->filterByEditionId($edition->id)
            ->whereKey($vote->categoryId)
            ->first();

        if ($category === null) {
            throw ValidationException::withMessages([
                'categories' => "Categoria {$vote->categoryId} nu face parte din această ediție.",
            ]);
        }

        $shortlist = ShortlistEntry::query()
            ->forEdition($edition)
            ->forCategory($category)
            ->get();

        if ($shortlist->isEmpty()) {
            throw ValidationException::withMessages([
                'categories' => "Categoria „{$category->name}” nu are o listă scurtă de nominalizați.",
            ]);
        }

        /** @var array<int, ShortlistEntry> $entriesByNomineeId */
        $entriesByNomineeId = [];
        foreach ($shortlist as $entry) {
            $entriesByNomineeId[(int) $entry->nominee_id] = $entry;
        }

        $submitted = $vote->nomineeIds;

        // Must be exactly N distinct picks from the shortlist, in order (PHAZE 4): N = 3, or the whole
        // shortlist if it holds fewer than 3.
        $required = min(PublicRankPoints::RANKS, $shortlist->count());

        $rightCount = count($submitted) === $required;
        $distinct = count(array_unique($submitted)) === count($submitted);
        $onShortlist = array_diff($submitted, array_keys($entriesByNomineeId)) === [];

        if (! $rightCount || ! $distinct || ! $onShortlist) {
            throw ValidationException::withMessages([
                'categories' => "La categoria „{$category->name}” trebuie să clasezi exact {$required} nominalizați din lista scurtă, în ordinea preferinței, fiecare o singură dată.",
            ]);
        }

        return [
            'category' => $category,
            'entriesByNomineeId' => $entriesByNomineeId,
            'nomineeIds' => $submitted,
        ];
    }
}
