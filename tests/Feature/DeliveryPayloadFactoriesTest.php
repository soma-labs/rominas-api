<?php

declare(strict_types=1);

use Rominas\Delivery\Brevo\BrevoMailPayload;
use Rominas\Delivery\SMTP\SmtpMailPayload;

it('gives the Brevo factory of each e-mail the same content as its SMTP twin', function (string $action, array $params, string $tag): void {
    $smtpFactory = config("delivery.transports.smtp.factories.{$action}");
    $brevoFactory = config("delivery.transports.brevo.factories.{$action}");

    $smtp = (new $smtpFactory(...$params))->create();
    $brevo = (new $brevoFactory(...$params))->create();

    expect($smtp)->toBeInstanceOf(SmtpMailPayload::class)
        ->and($brevo)->toBeInstanceOf(BrevoMailPayload::class)
        ->and($brevo->sender)->toEqual($smtp->sender)
        ->and($brevo->recipients)->toBe(['tester@example.test'])
        ->and($brevo->recipients)->toBe($smtp->recipients)
        ->and($brevo->subject)->toBe($smtp->subject)
        ->and($brevo->body)->toBe($smtp->body)
        ->and($brevo->tags)->toBe([$tag]);
})->with([
    'invitation' => ['academy-invitation-email', ['tester@example.test', 'link-token'], 'academy-invitation'],
    'login link' => ['academy-magic-link-email', ['tester@example.test', 'link-token'], 'academy-login'],
    'voting link' => ['voting-link-email', ['tester@example.test', 'link-token'], 'voting-link'],
    'notification' => [
        'notification-email',
        ['tester@example.test', 'Ana Popescu joined the academy', 'emails.notifications.admin', ['title' => 'Ana Popescu joined the academy', 'body' => 'Ana Popescu activated their account.', 'url' => 'http://localhost:3000/members/1']],
        'admin',
    ],
    'example' => ['example-notification', ['tester@example.test', 'Subject', '<p>Body</p>'], 'example-notification'],
]);

it('offers the same e-mails on both transports', function (): void {
    expect(array_keys(config('delivery.transports.brevo.factories')))
        ->toEqualCanonicalizing(array_keys(config('delivery.transports.smtp.factories')));
});
