<?php

declare(strict_types=1);

namespace Rominas\Delivery\SMTP;

use Rominas\Delivery\Factories\AcademyInvitationEmailPayloadFactory;

/**
 * The academy invitation e-mail sent over SMTP. The content lives in {@see AcademyInvitationEmailPayloadFactory}.
 */
class SmtpAcademyInvitationEmailPayloadFactory extends AcademyInvitationEmailPayloadFactory
{
    use BuildsSmtpMailPayload;
}
