<?php

declare(strict_types=1);

namespace Rominas\Delivery\Brevo\Factories;

use Rominas\Delivery\Factories\AcademyMagicLinkEmailPayloadFactory;
use Rominas\Delivery\Brevo\BuildsBrevoMailPayload;

/**
 * The academy login link e-mail sent through the Brevo API. The content lives in {@see AcademyMagicLinkEmailPayloadFactory}.
 */
class BrevoAcademyMagicLinkEmailPayloadFactory extends AcademyMagicLinkEmailPayloadFactory
{
    use BuildsBrevoMailPayload;
}
