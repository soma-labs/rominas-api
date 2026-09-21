<?php

declare(strict_types=1);

namespace Rominas\Academy\Nomination\DataTransferObjects;

class CategoryRankingData
{
    /**
     * @param  list<string>  $nomineeNames  free-text nominee names in rank order (index 0 = rank 1 = top)
     */
    public function __construct(
        public int $categoryId,
        public array $nomineeNames,
    ) {}
}
