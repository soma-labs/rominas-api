<?php

declare(strict_types=1);

namespace Rominas\Delivery\SMTP;

use Rominas\Delivery\DeliveryPayloadInterface;
use Rominas\Delivery\EmailContact;
use Rominas\Delivery\PayloadFactoryInterface;

/**
 * Renders any notification e-mail (Notifications module): the notification supplies the subject and
 * the Blade view with its data, so one factory serves every notification type.
 */
class SmtpNotificationEmailPayloadFactory implements PayloadFactoryInterface
{
    /**
     * @param  array<string, mixed>  $viewData
     */
    public function __construct(
        private readonly string $email,
        private readonly string $subject,
        private readonly string $view,
        private readonly array $viewData = [],
    ) {}

    public function create(): DeliveryPayloadInterface
    {
        return new SmtpMailPayload(
            sender: new EmailContact(
                email: config('mail.from.address'),
                name: config('mail.from.name'),
            ),
            recipients: [$this->email],
            subject: $this->subject,
            body: view($this->view, $this->viewData)->render(),
        );
    }
}
