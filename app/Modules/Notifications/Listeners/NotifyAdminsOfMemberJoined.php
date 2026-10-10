<?php

declare(strict_types=1);

namespace Rominas\Notifications\Listeners;

use Rominas\Academy\Member\Events\MemberActivated;
use Rominas\Notifications\Actions\NotifyAdminsWithPermissionAction;
use Rominas\Notifications\Notifications\MemberJoined;

/**
 * Tells the admins who manage academy members that an invited member activated their account. Wired
 * in EventServiceProvider.
 */
class NotifyAdminsOfMemberJoined
{
    public function __construct(
        private readonly NotifyAdminsWithPermissionAction $notifyAdmins,
    ) {}

    public function handle(MemberActivated $event): void
    {
        $this->notifyAdmins->execute('members', new MemberJoined(
            memberId: $event->member->id,
            memberName: $event->member->name,
        ));
    }
}
