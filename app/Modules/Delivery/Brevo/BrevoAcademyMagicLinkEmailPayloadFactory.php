<?php

declare(strict_types=1);

namespace Rominas\Delivery\Brevo;

use Rominas\Delivery\Factories\AcademyMagicLinkEmailPayloadFactory;

/**
 * The academy login link e-mail sent through the Brevo API. The content lives in {@see AcademyMagicLinkEmailPayloadFactory}.
 */
class BrevoAcademyMagicLinkEmailPayloadFactory extends AcademyMagicLinkEmailPayloadFactory
{
    use BuildsBrevoMailPayload;
}
