<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\DataTransferObjects;

class MemberImportResultData
{
    /**
     * @param  list<MemberImportSkippedRowData>  $skipped
     */
    public function __construct(
        public int $created,
        public array $skipped,
    ) {}
}
