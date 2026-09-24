<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Enums;

/**
 * Lifecycle of an academy member account. A member starts `AwaitingInvitation` (on the roster, no
 * invitation sent yet); an admin sending the magic-link invitation moves them to `Invited`; they become
 * `Active` on their first successful magic-link login. `Suspended` members are barred from logging in.
 */
enum MemberStatus: string
{
    case AwaitingInvitation = 'awaiting_invitation';
    case Invited = 'invited';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingInvitation => 'Awaiting invitation',
            self::Invited => 'Invited',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
        };
    }
}
