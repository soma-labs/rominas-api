<?php

declare(strict_types=1);

namespace Rominas\Categories\DataTransferObjects;

use Rominas\Catalog\Enums\NomineeType;
use Rominas\Categories\Enums\CategoryKind;

class CategoryData
{
    public function __construct(
        public int $edition_id,
        public string $name,
        public CategoryKind $kind,
        public ?NomineeType $nominee_type,
        public int $position = 0,
        public ?string $description = null,
    ) {}
}
