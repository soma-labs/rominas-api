<?php

declare(strict_types=1);

namespace Rominas\Voting\Actions;

use Rominas\EmailValidation\DataTransferObjects\EmailValidationResult;
use Rominas\Voting\Enums\EmailCheckDecision;

/**
 * Turns a validation result into the voting decision, from the `voting.email_validation.decisions` table
 * (status → sub-status → accept / flag / reject). A sub-status the table does not list takes its status's
 * own row; a status it does not list at all is flagged, so a code the service adds later never silently
 * blocks or waves through a voter.
 */
class ResolveEmailCheckDecisionAction
{
    public function execute(EmailValidationResult $result): EmailCheckDecision
    {
        /** @var array<string, array<string, string>> $decisions */
        $decisions = config('voting.email_validation.decisions', []);

        $forStatus = $decisions[$result->status] ?? [];

        $decision = $forStatus[$result->subStatus ?? ''] ?? $forStatus[''] ?? 'flag';

        return match ($decision) {
            'accept' => EmailCheckDecision::Accepted,
            'reject' => EmailCheckDecision::Rejected,
            default => EmailCheckDecision::AcceptedWithNote,
        };
    }
}
