<?php

declare(strict_types=1);

namespace Rominas\Delivery\SMTP;

use Rominas\Delivery\Factories\AcademyMagicLinkEmailPayloadFactory;

/**
 * The academy login link e-mail sent over SMTP. The content lives in {@see AcademyMagicLinkEmailPayloadFactory}.
 */
class SmtpAcademyMagicLinkEmailPayloadFactory extends AcademyMagicLinkEmailPayloadFactory
{
    use BuildsSmtpMailPayload;
}
