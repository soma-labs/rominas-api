<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\DataTransferObjects;

use Rominas\Academy\Member\Model\Member;
use Rominas\Editions\Model\Edition;

/**
 * The special awards of the active edition as one member sees them.
 */
final class MemberSpecialAwardsData
{
    /**
     * @param  list<MemberSpecialCategoryData>  $categories  in category order
     */
    public function __construct(
        public readonly Edition $edition,
        public readonly Member $member,
        public readonly array $categories,
    ) {}
}
