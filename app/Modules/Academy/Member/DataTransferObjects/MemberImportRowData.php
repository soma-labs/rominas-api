<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\DataTransferObjects;

/**
 * One row of an uploaded member list, as read from the file: cells trimmed, blanks as null.
 */
class MemberImportRowData
{
    public function __construct(
        public int $rowNumber,
        public ?string $name,
        public ?string $email,
        public ?string $position = null,
        public ?string $company = null,
        public ?string $phone = null,
    ) {}
}
