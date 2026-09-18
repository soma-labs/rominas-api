<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\MemberProposal\Enums\MemberProposalStatus;
use Rominas\Academy\MemberProposal\Model\MemberProposal;

/**
 * @extends Factory<MemberProposal>
 */
class MemberProposalFactory extends Factory
{
    protected $model = MemberProposal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proposed_by_member_id' => Member::factory()->active(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'position' => fake()->optional()->jobTitle(),
            'company' => fake()->optional()->company(),
            'phone' => fake()->optional()->phoneNumber(),
            'reason' => fake()->optional()->sentence(),
            'status' => MemberProposalStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn(array $attributes): array => [
            'status' => MemberProposalStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn(array $attributes): array => [
            'status' => MemberProposalStatus::Rejected,
            'reviewed_at' => now(),
        ]);
    }
}
