<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'status' => MemberStatus::Active,
            'email_verified_at' => now(),
            'invited_at' => now(),
            'activated_at' => now(),
        ];
    }

    public function awaitingInvitation(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => MemberStatus::AwaitingInvitation,
            'email_verified_at' => null,
            'invited_at' => null,
            'activated_at' => null,
        ]);
    }

    public function invited(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => MemberStatus::Invited,
            'email_verified_at' => null,
            'invited_at' => now(),
            'activated_at' => null,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => MemberStatus::Active,
            'email_verified_at' => now(),
            'activated_at' => now(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => MemberStatus::Suspended,
        ]);
    }
}
