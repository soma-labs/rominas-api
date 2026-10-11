<?php

declare(strict_types=1);

namespace Rominas\Academy\Nomination\DataTransferObjects;

/**
 * A Catalog entry offered to a member while typing a nominee name.
 */
class NomineeSuggestionData
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
