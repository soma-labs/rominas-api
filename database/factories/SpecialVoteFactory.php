<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Rominas\Academy\Member\Model\Member;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;
use Rominas\SpecialAwards\Vote\Model\SpecialVote;

/**
 * @extends Factory<SpecialVote>
 */
class SpecialVoteFactory extends Factory
{
    protected $model = SpecialVote::class;

    /**
     * A vote for an approved entry, in that entry's category.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'special_entry_id' => SpecialEntry::factory()->approved(),
            'category_id' => fn(array $attributes): int => (int) SpecialEntry::query()
                ->findOrFail($attributes['special_entry_id'])
                ->category_id,
            'member_id' => Member::factory()->active(),
        ];
    }
}
