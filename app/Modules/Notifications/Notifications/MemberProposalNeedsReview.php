<?php

declare(strict_types=1);

namespace Rominas\Notifications\Notifications;

use Rominas\Notifications\Enums\NotificationType;

/**
 * A member proposed a future academy member and the proposal waits for an admin's review.
 */
class MemberProposalNeedsReview extends AdminNotification
{
    public function __construct(
        public readonly int $memberProposalId,
        public readonly string $proposedName,
        public readonly string $proposerName,
    ) {
        $this->afterCommit();
    }

    public function type(): NotificationType
    {
        return NotificationType::MemberProposalNeedsReview;
    }

    public function title(): string
    {
        return "New member proposal: {$this->proposedName}";
    }

    public function body(): string
    {
        return "{$this->proposerName} proposed {$this->proposedName} as a future Academy member. The proposal is waiting for review.";
    }

    public function url(): string
    {
        return '/member-proposals';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [...parent::toArray($notifiable), 'member_proposal_id' => $this->memberProposalId];
    }
}
