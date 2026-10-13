<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Rominas\Categories\Model\Category;
use Rominas\Results\Model\ResultSnapshot;
use Rominas\Results\Model\SpecialResultEntry;

/**
 * @extends Factory<SpecialResultEntry>
 */
class SpecialResultEntryFactory extends Factory
{
    protected $model = SpecialResultEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'result_snapshot_id' => ResultSnapshot::factory(),
            'category_id' => Category::factory()->proposeOrVote(),
            'title' => fake()->name(),
            'votes' => fake()->numberBetween(0, 25),
            'position' => 1,
        ];
    }
}
