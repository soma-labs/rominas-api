<?php

declare(strict_types=1);

namespace Rominas\Delivery\Factories;

use Rominas\Delivery\Enums\EmailAudience;

/**
 * The one-time announcement that the nominations and gala calendar is postponed, in the client's text.
 */
class AcademyCalendarUpdateEmailPayloadFactory extends MailPayloadFactory
{
    public function __construct(private readonly string $email) {}

    protected function senderName(): string
    {
        return EmailAudience::Academy->senderName();
    }

    protected function recipients(): array
    {
        return [$this->email];
    }

    protected function subject(): string
    {
        return 'Update privind calendarul Romanian Music Industry Academy Awards';
    }

    protected function body(): string
    {
        return view('emails.academy.calendar-update')->render();
    }

    protected function tags(): array
    {
        return ['academy-calendar-update'];
    }
}
