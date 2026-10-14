<?php

declare(strict_types=1);

namespace Rominas\Delivery\SMTP\Factories;

use Rominas\Delivery\Factories\AcademyMagicLinkEmailPayloadFactory;
use Rominas\Delivery\SMTP\BuildsSmtpMailPayload;

/**
 * The academy login link e-mail sent over SMTP. The content lives in {@see AcademyMagicLinkEmailPayloadFactory}.
 */
class SmtpAcademyMagicLinkEmailPayloadFactory extends AcademyMagicLinkEmailPayloadFactory
{
    use BuildsSmtpMailPayload;
}
