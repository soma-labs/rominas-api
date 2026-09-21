<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Rominas\Catalog\Artist\Model\Artist;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Catalog\NomineeSubmission\Enums\NomineeSubmissionStatus;
use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;
use Rominas\Editions\Model\Edition;
use Rominas\Users\Model\User;

/**
 * @extends Factory<NomineeSubmission>
 */
class NomineeSubmissionFactory extends Factory
{
    protected $model = NomineeSubmission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'edition_id' => Edition::factory(),
            'nominee_type' => NomineeType::Artist,
            'raw_name' => $name,
            'normalized_name' => Str::slug($name),
            'status' => NomineeSubmissionStatus::Pending,
            'resolved_nominee_id' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'review_note' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn(array $attributes): array => [
            'status' => NomineeSubmissionStatus::Resolved,
            'resolved_nominee_id' => Artist::factory(),
            'reviewed_by_user_id' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn(array $attributes): array => [
            'status' => NomineeSubmissionStatus::Rejected,
            'reviewed_by_user_id' => User::factory(),
            'reviewed_at' => now(),
            'review_note' => 'Not a valid nominee.',
        ]);
    }
}
