<?php

declare(strict_types=1);

namespace Rominas\Editions\Enums;

/**
 * The edition lifecycle (ecosystem CLAUDE.md §8). Transitions are linear; `archived`
 * is terminal. An edition is "active" while it is anything other than archived — at
 * most one edition may be non-archived at a time (enforced on create).
 */
enum EditionStatus: string
{
    case Draft = 'draft';
    case NominationsOpen = 'nominations_open';
    case NominationsClosed = 'nominations_closed';
    case VotingOpen = 'voting_open';
    case VotingClosed = 'voting_closed';
    case CommitteeReview = 'committee_review';
    case ResultsPublished = 'results_published';
    case Archived = 'archived';

    public function isActive(): bool
    {
        return $this !== self::Archived;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::NominationsOpen => 'Nominations open',
            self::NominationsClosed => 'Nominations closed',
            self::VotingOpen => 'Voting open',
            self::VotingClosed => 'Voting closed',
            self::CommitteeReview => 'Committee review',
            self::ResultsPublished => 'Results published',
            self::Archived => 'Archived',
        };
    }

    /**
     * Statuses this one may transition to next.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::NominationsOpen],
            self::NominationsOpen => [self::NominationsClosed],
            self::NominationsClosed => [self::VotingOpen],
            self::VotingOpen => [self::VotingClosed],
            self::VotingClosed => [self::CommitteeReview],
            self::CommitteeReview => [self::ResultsPublished],
            self::ResultsPublished => [self::Archived],
            self::Archived => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
