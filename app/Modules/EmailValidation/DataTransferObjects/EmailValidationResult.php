<?php

declare(strict_types=1);

namespace Rominas\EmailValidation\DataTransferObjects;

/**
 * The validation service's verdict on one address: its status and, when given, the sub-status that
 * narrows it. Both are the service's own codes, kept as-is. Carries no personal data.
 */
final class EmailValidationResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $subStatus = null,
    ) {}
}
