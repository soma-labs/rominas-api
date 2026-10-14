<?php

declare(strict_types=1);

namespace Rominas\Delivery\Brevo\Factories;

use Rominas\Delivery\Factories\AcademyInvitationEmailPayloadFactory;
use Rominas\Delivery\Brevo\BuildsBrevoMailPayload;

/**
 * The academy invitation e-mail sent through the Brevo API. The content lives in {@see AcademyInvitationEmailPayloadFactory}.
 */
class BrevoAcademyInvitationEmailPayloadFactory extends AcademyInvitationEmailPayloadFactory
{
    use BuildsBrevoMailPayload;
}
