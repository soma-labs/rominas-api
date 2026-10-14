<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\DataTransferObjects;

class MemberImportSkippedRowData
{
    public function __construct(
        public int $rowNumber,
        public ?string $email,
        public string $reason,
    ) {}
}
