<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Categories\Enums\CategoryKind;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Model\Edition;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Best ' . fake()->unique()->words(2, true);

        return [
            'edition_id' => Edition::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'nominee_type' => fake()->randomElement(NomineeType::cases()),
            'position' => 0,
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function proposeOrVote(): static
    {
        return $this->state(fn(): array => [
            'kind' => CategoryKind::ProposeOrVote,
            'nominee_type' => null,
        ]);
    }

    public function appointed(): static
    {
        return $this->state(fn(): array => [
            'kind' => CategoryKind::Appointed,
            'nominee_type' => null,
        ]);
    }
}
