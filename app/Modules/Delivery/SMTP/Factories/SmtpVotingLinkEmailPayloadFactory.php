<?php

declare(strict_types=1);

namespace Rominas\Delivery\SMTP\Factories;

use Rominas\Delivery\Factories\VotingLinkEmailPayloadFactory;
use Rominas\Delivery\SMTP\BuildsSmtpMailPayload;

/**
 * The voting link e-mail sent over SMTP. The content lives in {@see VotingLinkEmailPayloadFactory}.
 */
class SmtpVotingLinkEmailPayloadFactory extends VotingLinkEmailPayloadFactory
{
    use BuildsSmtpMailPayload;
}
