<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Entry\DataTransferObjects;

class SpecialEntryData
{
    public function __construct(
        public string $title,
    ) {}
}
