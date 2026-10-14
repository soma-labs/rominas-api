<?php

declare(strict_types=1);

use Rominas\Delivery\Brevo\BrevoMailService;
use Rominas\Delivery\Factories\AcademyInvitationEmailPayloadFactory;
use Rominas\Delivery\Factories\AcademyMagicLinkEmailPayloadFactory;
use Rominas\Delivery\Factories\ExampleNotificationPayloadFactory;
use Rominas\Delivery\Factories\NotificationEmailPayloadFactory;
use Rominas\Delivery\Factories\VotingLinkEmailPayloadFactory;
use Rominas\Delivery\SMTP\SmtpMailService;

/*
|--------------------------------------------------------------------------
| E-mail transports
|--------------------------------------------------------------------------
|
| Each transport is the service that sends a MailPayload. Every e-mail is built
| by the same factory whichever transport is in use, so switching changes only
| how the e-mail travels: `smtp` = the MAIL_* SMTP server (e.g. Brevo's SMTP
| relay), `brevo` = Brevo's HTTP API with BREVO_API_KEY.
|
*/

$transports = [
    'smtp' => SmtpMailService::class,
    'brevo' => BrevoMailService::class,
];

$transport = env('MAIL_DELIVERY_TRANSPORT', 'smtp');

return [

    /*
    | The e-mail transport in use: `smtp` (default) or `brevo`. Any other value stops the app at boot
    | (AppServiceProvider). After changing it on a server: config:cache, then restart the queue worker.
    */
    'transport' => $transport,

    'transports' => $transports,

    /*
    |--------------------------------------------------------------------------
    | Delivery methods
    |--------------------------------------------------------------------------
    |
    | DeliveryAction::execute($action, ['email'], ...$params) looks up
    | methods.email.factories[$action], instantiates it with $params, and sends
    | the payload through methods.email.service.
    |
    */

    'methods' => [
        'email' => [
            'service' => $transports[$transport] ?? null,
            'factories' => [
                'example-notification' => ExampleNotificationPayloadFactory::class,
                'academy-invitation-email' => AcademyInvitationEmailPayloadFactory::class,
                'academy-magic-link-email' => AcademyMagicLinkEmailPayloadFactory::class,
                'voting-link-email' => VotingLinkEmailPayloadFactory::class,
                // Every Notifications-module e-mail: the notification supplies subject + view.
                'notification-email' => NotificationEmailPayloadFactory::class,
            ],
        ],
    ],

];
