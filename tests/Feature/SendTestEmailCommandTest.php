<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Rominas\Delivery\Actions\DeliveryAction;
use Rominas\Delivery\DeliveryPayloadInterface;
use Rominas\Delivery\DeliveryServiceInterface;
use Rominas\Delivery\MailPayload;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travelTo;

/**
 * Stands in for the SMTP transport, which leaves the process: keeps the payloads it is asked to send.
 */
final class TestMailRecordingDeliveryService implements DeliveryServiceInterface
{
    /** @var list<MailPayload> */
    public array $payloads = [];

    public function send(DeliveryPayloadInterface $payload): void
    {
        assert($payload instanceof MailPayload);

        $this->payloads[] = $payload;
    }
}

/**
 * Refuses every message the way an SMTP server does.
 */
final class TestMailRefusingDeliveryService implements DeliveryServiceInterface
{
    public function send(DeliveryPayloadInterface $payload): void
    {
        throw new RuntimeException('Expected response code "250" but got code "535", with message "535 5.7.8 Authentication failed".');
    }
}

/**
 * Route the Delivery module's e-mails to the given service instead of SMTP, keeping the real payload factories.
 *
 * @param  class-string<DeliveryServiceInterface>  $service
 */
function testMailDeliverThrough(string $service): void
{
    app()->when(DeliveryAction::class)
        ->needs('$availableDeliveryMethods')
        ->give([
            'email' => [
                'service' => $service,
                'factories' => config('delivery.methods.email.factories'),
            ],
        ]);
}

function testMailRecordDeliveries(): TestMailRecordingDeliveryService
{
    $recorder = new TestMailRecordingDeliveryService();
    app()->instance(TestMailRecordingDeliveryService::class, $recorder);
    testMailDeliverThrough(TestMailRecordingDeliveryService::class);

    return $recorder;
}

it('sends the chosen template to the given address', function (string $template, string $subject): void {
    travelTo('2026-10-14 09:00:00');
    $sent = testMailRecordDeliveries();

    artisan('rominas:test-mail', ['email' => 'tester@example.test', 'template' => $template])->assertSuccessful();

    expect($sent->payloads)->toHaveCount(1)
        ->and($sent->payloads[0]->recipients)->toBe(['tester@example.test'])
        ->and($sent->payloads[0]->subject)->toBe($subject);
})->with([
    ['academy-invitation', 'Invitație în Academia The Romanian Music Industry Awards'],
    ['academy-invitation-proposed', 'Invitație în Academia The Romanian Music Industry Awards'],
    ['academy-login', 'Link de autentificare în Academia The Romanian Music Industry Awards'],
    ['voting-link', 'Link de vot The Romanian Music Industry Awards'],
    ['nominations-opened', 'Nominalizările Romias 2026 sunt deschise!'],
    ['nomination-reminder-3', 'Mai sunt 3 zile pentru nominalizările Romias 2026!'],
    ['nomination-reminder-1', 'Mâine se încheie nominalizările Romias 2026!'],
    ['admin-member-proposal', 'New member proposal: Ana Popescu'],
    ['admin-member-joined', 'Ana Popescu joined the academy'],
    ['admin-fraud-alerts', '3 new fraud alerts for Romias 2026'],
    ['admin-daily-statistics', 'Daily statistics for Romias 2026: 13 Oct 2026'],
]);

it('names a sample proposer only in the proposal variant of the invitation', function (): void {
    $sent = testMailRecordDeliveries();

    artisan('rominas:test-mail', ['email' => 'tester@example.test', 'template' => 'academy-invitation'])->assertSuccessful();
    artisan('rominas:test-mail', ['email' => 'tester@example.test', 'template' => 'academy-invitation-proposed'])->assertSuccessful();

    expect($sent->payloads[0]->body)->not->toContain('de către')
        ->and($sent->payloads[1]->body)->toContain('de către Ion Ionescu');
});

it('sends every template when asked for all of them', function (): void {
    $sent = testMailRecordDeliveries();

    artisan('rominas:test-mail', ['email' => 'tester@example.test', 'template' => 'all'])->assertSuccessful();

    expect($sent->payloads)->toHaveCount(11);
});

it('refuses an invalid address or an unknown template without sending', function (string $email, string $template): void {
    $sent = testMailRecordDeliveries();

    artisan('rominas:test-mail', ['email' => $email, 'template' => $template])->assertFailed();

    expect($sent->payloads)->toBe([]);
})->with([
    'invalid address' => ['not-an-address', 'voting-link'],
    'unknown template' => ['tester@example.test', 'no-such-template'],
]);

it('asks for the address and the template when they are not given', function (): void {
    $sent = testMailRecordDeliveries();

    artisan('rominas:test-mail')
        ->expectsQuestion('Which address should receive the e-mail?', 'tester@example.test')
        ->expectsQuestion('Which template?', 'voting-link')
        ->assertSuccessful();

    expect($sent->payloads)->toHaveCount(1)
        ->and($sent->payloads[0]->recipients)->toBe(['tester@example.test']);
});

it('reports the mail server\'s reply when sending fails', function (): void {
    Log::shouldReceive('channel')->with('delivery')->andReturnSelf();
    Log::shouldReceive('error');
    testMailDeliverThrough(TestMailRefusingDeliveryService::class);

    artisan('rominas:test-mail', ['email' => 'tester@example.test', 'template' => 'voting-link'])
        ->expectsOutputToContain('535 5.7.8 Authentication failed')
        ->assertFailed();
});
