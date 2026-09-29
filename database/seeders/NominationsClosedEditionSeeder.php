<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\MemberProposal\Model\MemberProposal;
use Rominas\Academy\Nomination\Enums\NominationStatus;
use Rominas\Academy\Nomination\Model\Nomination;
use Rominas\Academy\Nomination\Model\NominationRanking;
use Rominas\Auth\MagicLink\Model\MagicLinkToken;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Catalog\NomineeSubmission\Actions\ResolveOrCreateNomineeSubmissionAction;
use Rominas\Catalog\NomineeSubmission\Enums\NomineeSubmissionStatus;
use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;
use Rominas\Catalog\NomineeSubmission\Support\NomineeNameNormalizer;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Actions\TransitionEditionAction;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;

/**
 * Dev data for everything after the academy round: an edition in `nominations_closed`, fresh active
 * members, and a submitted nomination per member covering every category with 5 ranked picks. Every typed
 * name stays a `pending` NomineeSubmission, so reconciliation (and the shortlist interlock) has real work.
 *
 * Starts from a clean slate: all members (with their proposals and member auth tokens), categories (with
 * everything hanging off them — rankings, shortlist, ballot rankings, result entries), Catalog entities and
 * nominee submissions are deleted first. Editions are never deleted: the active one is reused — moved
 * forward to `nominations_closed` when still `draft` / `nominations_open`, otherwise its status is left
 * alone — and the default category set is created for it.
 * Names are typed as spelling variants of a fixed pool, so dedup, match suggestions and link/create all
 * get exercised; about half of each pool also exists in the Catalog to link against.
 *
 * Run on demand: `sail artisan db:seed --class=NominationsClosedEditionSeeder`.
 */
class NominationsClosedEditionSeeder extends Seeder
{
    private const int MEMBER_COUNT = 5;

    private const int PICKS_PER_CATEGORY = 5;

    /**
     * @var list<array{name: string, nominee_type: NomineeType}>
     */
    private const array DEFAULT_CATEGORIES = [
        ['name' => 'Best Artist', 'nominee_type' => NomineeType::Artist],
        ['name' => 'Best Band', 'nominee_type' => NomineeType::Band],
        ['name' => 'Song of the Year', 'nominee_type' => NomineeType::Song],
        /* ['name' => 'Album of the Year', 'nominee_type' => NomineeType::Album], */
        /* ['name' => 'Best Live Venue', 'nominee_type' => NomineeType::Venue], */
    ];

    /**
     * Canonical names per nominee type, most popular first (earlier names are picked more often).
     *
     * @var array<string, list<string>>
     */
    private const array NAME_POOLS = [
        'artist' => [
            'Delia Matache', 'Smiley', 'Irina Rimes', 'Carla\'s Dreams', 'Inna', 'Alexandra Stan',
            'Andra', 'Theo Rose', 'Feli Donose', 'Marius Moga', 'Antonia', 'Lora',
        ],
        'band' => [
            'Vama', 'Subcarpați', 'Voltaj', 'Holograf', 'Phoenix', 'Iris',
            'Coma', 'Robin and the Backstabbers', 'Byron', 'The Motans', 'Taxi', 'Luna Amară',
        ],
        'song' => [
            'Lumea ta', 'Nu mă uita', 'Dragostea din tei', 'Ploaia din noiembrie', 'Cine m-a făcut om mare',
            'Nu mai am timp', 'Acasă', 'Sus pe munte', 'Arde orașul', 'Toată lumea danseaza', 'Inima nebună',
            'Fără tine',
        ],
        'album' => [
            'Acasă în România', 'Poveste de iarnă', 'Nopți albe', 'Drumul spre casă', 'Vara nu dorm',
            'Culori', 'Oameni ca noi', 'Liniște', 'Orașul de sticlă', 'Ecou', 'Anotimpuri', 'Rădăcini',
        ],
        'venue' => [
            'Arenele Romane', 'Sala Palatului', 'Quantic Club', 'Form Space', 'Control Club',
            'Berăria H', 'Hard Rock Cafe București', 'Expirat Halele Carol', 'Arena Națională',
            'Grădina Monteoru', 'Club Fabrica', 'Teatrul de Vară Herăstrău',
        ],
    ];

    public function __construct(
        private readonly TransitionEditionAction $transitionEdition,
        private readonly ResolveOrCreateNomineeSubmissionAction $resolveSubmission,
    ) {}

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->deleteExistingData();

            $existingEdition = Edition::query()->active()->first();
            $edition = $existingEdition ?? $this->createEdition();
            $statusKept = $this->closeNominations($edition);

            $categories = $this->createCategories($edition);
            /* $this->seedCatalog($categories); */

            $members = Member::factory()->active()->count(self::MEMBER_COUNT)->create();

            foreach ($members as $member) {
                $this->seedSubmittedNomination($member, $edition, $categories);
            }

            $this->command->info(sprintf(
                '%s edition "%s" (%s%s): %d members, %d submitted nominations, %d pending submissions.',
                $existingEdition === null ? 'Created' : 'Reused',
                $edition->name,
                $edition->status->value,
                $statusKept ? ', status kept' : '',
                $members->count(),
                $members->count(),
                $this->pendingSubmissionCount($edition),
            ));
        });
    }

    /**
     * A fresh draft edition whose nomination window has already passed and whose voting is still ahead.
     */
    private function createEdition(): Edition
    {
        $now = Carbon::now();

        return Edition::factory()->create([
            'starts_at' => $now->copy()->subWeeks(6),
            'nominations_start_at' => $now->copy()->subWeeks(5),
            'nominations_end_at' => $now->copy()->subDay(),
            'voting_start_at' => $now->copy()->addWeek(),
            'voting_end_at' => $now->copy()->addWeeks(3),
            'ends_at' => $now->copy()->addMonths(2),
            'status' => EditionStatus::Draft,
        ]);
    }

    /**
     * Steps a `draft` / `nominations_open` edition forward to `nominations_closed`, one legal transition at a
     * time. Returns true when the edition was already there or beyond, and its status was left alone.
     */
    private function closeNominations(Edition $edition): bool
    {
        if ($edition->status === EditionStatus::Draft) {
            $this->transitionEdition->execute($edition, EditionStatus::NominationsOpen);
        }

        if ($edition->status === EditionStatus::NominationsOpen) {
            $this->transitionEdition->execute($edition, EditionStatus::NominationsClosed);

            return false;
        }

        return true;
    }

    /**
     * Wipes the members, categories, Catalog and nomination tables. Deletes go through the query builder so the
     * FK cascades clear the dependants: nominations/rankings and proposals with their members; rankings,
     * shortlist, ballot rankings and result entries with their categories.
     */
    private function deleteExistingData(): void
    {
        NominationRanking::query()->delete();
        Nomination::query()->delete();
        NomineeSubmission::query()->delete();
        Category::query()->delete();

        foreach (NomineeType::cases() as $type) {
            $type->modelClass()::query()->delete();
        }

        MagicLinkToken::query()->where('guard', 'member')->delete();
        PersonalAccessToken::query()->where('tokenable_type', Member::class)->delete();
        MemberProposal::query()->delete();
        Member::query()->delete();
    }

    /**
     * @return Collection<int, Category>
     */
    private function createCategories(Edition $edition): Collection
    {
        return collect(self::DEFAULT_CATEGORIES)->map(
            fn(array $category, int $index): Category => Category::factory()->create([
                'edition_id' => $edition->id,
                'name' => $category['name'],
                'slug' => Str::slug($category['name']),
                'nominee_type' => $category['nominee_type'],
                'position' => $index + 1,
            ]),
        );
    }

    /**
     * Puts every other pool name of each used type into the Catalog, so reconciliation can both link to an
     * existing entity and create a new one. Idempotent by slug.
     *
     * @param  Collection<int, Category>  $categories
     */
    private function seedCatalog(Collection $categories): void
    {
        $types = $categories->map(fn(Category $category): NomineeType => $category->nominee_type)->unique();

        foreach ($types as $type) {
            $modelClass = $type->modelClass();

            foreach (self::NAME_POOLS[$type->value] as $index => $name) {
                if ($index % 2 === 0) {
                    $modelClass::query()->firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
                }
            }
        }
    }

    /**
     * @param  Collection<int, Category>  $categories
     */
    private function seedSubmittedNomination(Member $member, Edition $edition, Collection $categories): void
    {
        $nomination = Nomination::create([
            'member_id' => $member->id,
            'edition_id' => $edition->id,
            'status' => NominationStatus::Submitted,
            'submitted_at' => fake()->dateTimeBetween($edition->nominations_start_at, $edition->nominations_end_at),
        ]);

        foreach ($categories as $category) {
            foreach ($this->typedNamesFor($category->nominee_type) as $index => $typedName) {
                $submission = $this->resolveSubmission->execute($edition, $category->nominee_type, $typedName);

                $nomination->rankings()->create([
                    'category_id' => $category->id,
                    'rank' => $index + 1,
                    'nominee_submission_id' => $submission->id,
                    'nominee_type' => $category->nominee_type,
                    'nominee_id' => $submission->resolved_nominee_id,
                ]);
            }
        }
    }

    /**
     * Five distinct pool names, popular ones favoured, each typed as a spelling variant. No two of them
     * normalize alike (the same guard SaveCategoryRankingAction applies), so each maps to its own submission.
     *
     * @return list<string>
     */
    private function typedNamesFor(NomineeType $type): array
    {
        $typedNames = [];

        foreach ($this->weightedShuffle(self::NAME_POOLS[$type->value]) as $canonicalName) {
            foreach ([$this->typedVariantOf($canonicalName), $canonicalName] as $candidate) {
                $normalizedName = NomineeNameNormalizer::normalize($candidate);

                if (! isset($typedNames[$normalizedName])) {
                    $typedNames[$normalizedName] = $candidate;

                    break;
                }
            }

            if (count($typedNames) === self::PICKS_PER_CATEGORY) {
                break;
            }
        }

        return array_values($typedNames);
    }

    /**
     * The pool in a random order where earlier (more popular) names tend to come first.
     *
     * @param  list<string>  $pool
     * @return list<string>
     */
    private function weightedShuffle(array $pool): array
    {
        $weights = [];

        foreach ($pool as $index => $name) {
            $weights[$name] = count($pool) - $index;
        }

        $shuffled = [];

        while ($weights !== []) {
            $roll = random_int(1, array_sum($weights));

            foreach ($weights as $name => $weight) {
                $roll -= $weight;

                if ($roll <= 0) {
                    $shuffled[] = (string) $name;
                    unset($weights[$name]);

                    break;
                }
            }
        }

        return $shuffled;
    }

    /**
     * How a member might type a name: mostly exact; sometimes with different casing or stray whitespace
     * (normalizes to the same submission); sometimes with swapped word order or a typo (a new submission).
     */
    private function typedVariantOf(string $name): string
    {
        $words = explode(' ', $name);
        $roll = random_int(1, 100);

        return match (true) {
            $roll <= 55 => $name,
            $roll <= 70 => mb_strtolower($name),
            $roll <= 80 => '  ' . str_replace(' ', '  ', $name) . ' ',
            $roll <= 90 && count($words) > 1 => implode(' ', array_reverse($words)),
            default => $this->withTypo($name),
        };
    }

    /**
     * Drops one letter from the longest word (never its first letter, so the name stays recognisable).
     */
    private function withTypo(string $name): string
    {
        $words = explode(' ', $name);
        $longest = 0;

        foreach ($words as $index => $word) {
            if (mb_strlen($word) > mb_strlen($words[$longest])) {
                $longest = $index;
            }
        }

        $word = $words[$longest];

        if (mb_strlen($word) < 4) {
            return $name;
        }

        $position = random_int(1, mb_strlen($word) - 1);
        $words[$longest] = mb_substr($word, 0, $position) . mb_substr($word, $position + 1);

        return implode(' ', $words);
    }

    private function pendingSubmissionCount(Edition $edition): int
    {
        return NomineeSubmission::query()
            ->where('edition_id', $edition->id)
            ->where('status', NomineeSubmissionStatus::Pending)
            ->count();
    }
}
