<?php

declare(strict_types=1);

use Rominas\Delivery\Brevo\Factories\BrevoAcademyInvitationEmailPayloadFactory;
use Rominas\Delivery\Brevo\Factories\BrevoAcademyMagicLinkEmailPayloadFactory;
use Rominas\Delivery\Brevo\Factories\BrevoExampleNotificationPayloadFactory;
use Rominas\Delivery\Brevo\BrevoMailService;
use Rominas\Delivery\Brevo\Factories\BrevoNotificationEmailPayloadFactory;
use Rominas\Delivery\Brevo\Factories\BrevoVotingLinkEmailPayloadFactory;
use Rominas\Delivery\SMTP\Factories\SmtpAcademyInvitationEmailPayloadFactory;
use Rominas\Delivery\SMTP\Factories\SmtpAcademyMagicLinkEmailPayloadFactory;
use Rominas\Delivery\SMTP\Factories\SmtpExampleNotificationPayloadFactory;
use Rominas\Delivery\SMTP\SmtpMailService;
use Rominas\Delivery\SMTP\Factories\SmtpNotificationEmailPayloadFactory;
use Rominas\Delivery\SMTP\Factories\SmtpVotingLinkEmailPayloadFactory;

/*
|--------------------------------------------------------------------------
| E-mail transports
|--------------------------------------------------------------------------
|
| Each transport maps a `service` to one payload factory per action key. Both
| transports serve the same keys, and each SMTP / Brevo factory pair shares its
| content (app/Modules/Delivery/Factories), so switching changes only how the
| e-mail travels: `smtp` = the MAIL_* SMTP server (e.g. Brevo's SMTP relay),
| `brevo` = Brevo's HTTP API with BREVO_API_KEY.
|
*/

$transports = [
    'smtp' => [
        'service' => SmtpMailService::class,
        'factories' => [
            'example-notification' => SmtpExampleNotificationPayloadFactory::class,
            'academy-invitation-email' => SmtpAcademyInvitationEmailPayloadFactory::class,
            'academy-magic-link-email' => SmtpAcademyMagicLinkEmailPayloadFactory::class,
            'voting-link-email' => SmtpVotingLinkEmailPayloadFactory::class,
            // Every Notifications-module e-mail: the notification supplies subject + view.
            'notification-email' => SmtpNotificationEmailPayloadFactory::class,
        ],
    ],
    'brevo' => [
        'service' => BrevoMailService::class,
        'factories' => [
            'example-notification' => BrevoExampleNotificationPayloadFactory::class,
            'academy-invitation-email' => BrevoAcademyInvitationEmailPayloadFactory::class,
            'academy-magic-link-email' => BrevoAcademyMagicLinkEmailPayloadFactory::class,
            'voting-link-email' => BrevoVotingLinkEmailPayloadFactory::class,
            'notification-email' => BrevoNotificationEmailPayloadFactory::class,
        ],
    ],
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
        'email' => $transports[$transport] ?? null,
    ],

];
