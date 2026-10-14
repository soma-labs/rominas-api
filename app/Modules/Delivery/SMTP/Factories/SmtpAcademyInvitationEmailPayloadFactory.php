<?php

declare(strict_types=1);

namespace Rominas\Delivery\SMTP\Factories;

use Rominas\Delivery\Factories\AcademyInvitationEmailPayloadFactory;
use Rominas\Delivery\SMTP\BuildsSmtpMailPayload;

/**
 * The academy invitation e-mail sent over SMTP. The content lives in {@see AcademyInvitationEmailPayloadFactory}.
 */
class SmtpAcademyInvitationEmailPayloadFactory extends AcademyInvitationEmailPayloadFactory
{
    use BuildsSmtpMailPayload;
}
