<?php

declare(strict_types=1);

use Rominas\Delivery\SMTP\SmtpAcademyInvitationEmailPayloadFactory;
use Rominas\Delivery\SMTP\SmtpAcademyMagicLinkEmailPayloadFactory;
use Rominas\Delivery\SMTP\SmtpExampleNotificationPayloadFactory;
use Rominas\Delivery\SMTP\SmtpMailService;
use Rominas\Delivery\SMTP\SmtpVotingLinkEmailPayloadFactory;

return [

    /*
    |--------------------------------------------------------------------------
    | Delivery methods
    |--------------------------------------------------------------------------
    |
    | Each method maps a transport `service` to a set of payload `factories`.
    | DeliveryAction::execute($action, ['email'], ...$params) looks up
    | factories[$action], instantiates it with $params, and sends the payload
    | through `service`. Add Rominas-specific payload factories (OTP, voting
    | link, academy invitation) here as those flows land in Phase 2.
    |
    */

    'methods' => [
        'email' => [
            'service' => SmtpMailService::class,
            'factories' => [
                // action key => payload factory
                'example-notification' => SmtpExampleNotificationPayloadFactory::class,
                'academy-invitation-email' => SmtpAcademyInvitationEmailPayloadFactory::class,
                'academy-magic-link-email' => SmtpAcademyMagicLinkEmailPayloadFactory::class,
                'voting-link-email' => SmtpVotingLinkEmailPayloadFactory::class,
            ],
        ],

        // Brevo transport — swap the 'email' block above for this and enable
        // configureBrevoMail() in AppServiceProvider. Provide Brevo payload
        // factories (extending PayloadFactoryInterface, returning BrevoMailPayload):
        //
        // 'email' => [
        //     'service' => \Rominas\Delivery\Brevo\BrevoMailService::class,
        //     'factories' => [
        //         'academy-magic-link-email' => \Rominas\Delivery\Brevo\BrevoAcademyMagicLinkEmailPayloadFactory::class,
        //     ],
        // ],
    ],

];
