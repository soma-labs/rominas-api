<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\DataTransferObjects;

/**
 * One academy invitation template: what the admin sees (`label`) and what the e-mail is built from.
 */
readonly class InvitationTemplateData
{
    public function __construct(
        public string $key,
        public string $label,
        public string $view,
        public string $subject,
    ) {}
}
