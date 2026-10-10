<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Rominas\Academy\Member\Model\Member;

/**
 * Fired once per member, when their first successful magic-link sign-in activates the account (see
 * MemberAuthController::verify). Later sign-ins do not fire it. Listeners are wired explicitly in
 * EventServiceProvider (auto-discovery does not scan app/Modules).
 */
readonly class MemberActivated
{
    use Dispatchable;

    public function __construct(
        public Member $member,
    ) {}
}
