<?php

declare(strict_types=1);

namespace Rominas\Academy\Nomination\DataTransferObjects;

/**
 * One pick on the ballot. `nomineeId` is set when the member chose a Catalog entry from the suggestions
 * (the pick is linked to it at once); null when the name was typed and awaits reconciliation.
 */
class RankedNomineeData
{
    public function __construct(
        public string $name,
        public ?int $nomineeId = null,
    ) {}
}
