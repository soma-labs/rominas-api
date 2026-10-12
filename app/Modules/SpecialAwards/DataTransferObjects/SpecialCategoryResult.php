<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\DataTransferObjects;

/**
 * A special category's result: its entries best-first. Empty `entries` means nobody proposed anything
 * that was approved, or no result was entered for an appointed category.
 */
final class SpecialCategoryResult
{
    /**
     * @param  list<SpecialEntryResult>  $entries  ordered by position (1 = winner; a tie shares it)
     */
    public function __construct(
        public readonly int $categoryId,
        public readonly array $entries,
    ) {}
}
