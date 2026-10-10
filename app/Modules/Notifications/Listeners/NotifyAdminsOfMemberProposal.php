<?php

declare(strict_types=1);

namespace Rominas\Notifications\Listeners;

use Rominas\Academy\MemberProposal\Events\MemberProposalCreated;
use Rominas\Notifications\Actions\NotifyAdminsWithPermissionAction;
use Rominas\Notifications\Notifications\MemberProposalNeedsReview;

/**
 * Tells the admins who review member proposals that a new one arrived. Wired in EventServiceProvider.
 */
class NotifyAdminsOfMemberProposal
{
    public function __construct(
        private readonly NotifyAdminsWithPermissionAction $notifyAdmins,
    ) {}

    public function handle(MemberProposalCreated $event): void
    {
        $this->notifyAdmins->execute('memberProposals', new MemberProposalNeedsReview(
            memberProposalId: $event->proposal->id,
            proposedName: $event->proposal->name,
            proposerName: $event->proposer->name,
        ));
    }
}
