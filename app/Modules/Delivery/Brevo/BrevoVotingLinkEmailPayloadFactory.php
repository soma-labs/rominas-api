<?php

declare(strict_types=1);

namespace Rominas\Delivery\Brevo;

use Rominas\Delivery\Factories\VotingLinkEmailPayloadFactory;

/**
 * The voting link e-mail sent through the Brevo API. The content lives in {@see VotingLinkEmailPayloadFactory}.
 */
class BrevoVotingLinkEmailPayloadFactory extends VotingLinkEmailPayloadFactory
{
    use BuildsBrevoMailPayload;
}
