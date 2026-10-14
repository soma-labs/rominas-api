<?php

declare(strict_types=1);

namespace Rominas\Delivery\SMTP;

use Rominas\Delivery\Factories\VotingLinkEmailPayloadFactory;

/**
 * The voting link e-mail sent over SMTP. The content lives in {@see VotingLinkEmailPayloadFactory}.
 */
class SmtpVotingLinkEmailPayloadFactory extends VotingLinkEmailPayloadFactory
{
    use BuildsSmtpMailPayload;
}
