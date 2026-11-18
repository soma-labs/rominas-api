<?php

declare(strict_types=1);

namespace Rominas\Categories\DataTransferObjects;

use Rominas\Catalog\Enums\NomineeType;
use Rominas\Categories\Enums\CategoryKind;
use Rominas\Categories\Model\Category;

class CategoryData
{
    public function __construct(
        public int $edition_id,
        public string $name,
        public CategoryKind $kind,
        public ?NomineeType $nominee_type,
        public int $position = 0,
        public ?string $description = null,
        public ?string $placeholder = null,
        public ?string $eligibility_criteria = null,
        public bool $publicly_visible = true,
        public int $shortlist_length = Category::MAX_SHORTLIST_LENGTH,
    ) {}
}
