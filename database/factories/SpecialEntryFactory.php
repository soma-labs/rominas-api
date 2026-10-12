<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Rominas\Academy\Member\Model\Member;
use Rominas\Categories\Model\Category;
use Rominas\SpecialAwards\Entry\Enums\SpecialEntryStatus;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;

/**
 * @extends Factory<SpecialEntry>
 */
class SpecialEntryFactory extends Factory
{
    protected $model = SpecialEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory()->proposeOrVote(),
            'title' => fake()->unique()->name(),
            'description' => fake()->optional()->sentence(),
            'status' => SpecialEntryStatus::Pending,
            'proposed_by_member_id' => Member::factory()->active(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn(array $attributes): array => [
            'status' => SpecialEntryStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn(array $attributes): array => [
            'status' => SpecialEntryStatus::Rejected,
            'reviewed_at' => now(),
        ]);
    }
}
