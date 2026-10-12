<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\DataTransferObjects;

/**
 * One entry's standing in a special category. `votes` is the number of members who voted for it (its
 * proposer included), or null in an appointed category, where nobody voted. Entries with the same number
 * of votes share a `position`.
 */
final class SpecialEntryResult
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?int $votes,
        public readonly int $position,
    ) {}
}
