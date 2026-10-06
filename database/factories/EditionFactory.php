<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;

/**
 * @extends Factory<Edition>
 */
class EditionFactory extends Factory
{
    protected $model = Edition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Romias ' . fake()->unique()->numberBetween(2020, 2100);
        $startsAt = Carbon::parse(fake()->dateTimeBetween('-1 month', '+1 month'));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'starts_at' => $startsAt,
            'nominations_start_at' => $startsAt->copy()->addWeek(),
            'nominations_end_at' => $startsAt->copy()->addWeeks(3),
            'voting_start_at' => $startsAt->copy()->addWeeks(4),
            'voting_end_at' => $startsAt->copy()->addWeeks(6),
            'ends_at' => $startsAt->copy()->addMonths(3),
            'status' => EditionStatus::Draft,
            'academy_vote_weight' => 60,
            'public_vote_weight' => 40,
        ];
    }

    public function status(EditionStatus $status): static
    {
        return $this->state(fn(array $attributes): array => ['status' => $status]);
    }

    public function archived(): static
    {
        return $this->status(EditionStatus::Archived);
    }
}
