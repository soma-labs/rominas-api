<?php

declare(strict_types=1);

namespace Rominas\EmailValidation;

use Rominas\EmailValidation\DataTransferObjects\EmailValidationResult;

/**
 * Asks an external service whether an email address is real and safe to send to. The service only
 * reports what it found; what to do with each result is the caller's policy.
 */
interface EmailValidatorInterface
{
    /**
     * Null means the address could not be checked (service not configured, unreachable or answering
     * with an error) — never that the address is bad.
     */
    public function validate(string $email): ?EmailValidationResult;
}
