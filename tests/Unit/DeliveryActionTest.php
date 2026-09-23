<?php

declare(strict_types=1);

use Rominas\Delivery\Actions\DeliveryAction;
use Rominas\Delivery\DeliveryPayloadInterface;
use Rominas\Delivery\DeliveryServiceInterface;
use Rominas\Delivery\Exceptions\DeliveryFailedException;
use Rominas\Delivery\PayloadFactoryInterface;
use Tests\TestCase;

// Boot the framework (for the app() container) without a database.
uses(TestCase::class);

final class StubDeliveryPayload implements DeliveryPayloadInterface {}

final class StubPayloadFactory implements PayloadFactoryInterface
{
    public function create(): DeliveryPayloadInterface
    {
        return new StubDeliveryPayload();
    }
}

/**
 * Records how many times it was asked to send, so a test can assert the method was attempted.
 */
final class RecordingDeliveryService implements DeliveryServiceInterface
{
    public int $sent = 0;

    public function send(DeliveryPayloadInterface $payload): void
    {
        $this->sent++;
    }
}

final class FailingDeliveryService implements DeliveryServiceInterface
{
    public function send(DeliveryPayloadInterface $payload): void
    {
        throw new RuntimeException('SMTP auth failed');
    }
}

it('returns a success status when the delivery service sends', function (): void {
    $action = new DeliveryAction([
        'email' => ['service' => RecordingDeliveryService::class, 'factories' => ['welcome' => StubPayloadFactory::class]],
    ]);

    $statuses = $action->execute('welcome', ['email'], 'to@example.test');

    expect($statuses)->toBe(['email' => 'success']);
});

it('throws DeliveryFailedException when a delivery method fails', function (): void {
    $action = new DeliveryAction([
        'email' => ['service' => FailingDeliveryService::class, 'factories' => ['welcome' => StubPayloadFactory::class]],
    ]);

    $caught = null;

    try {
        $action->execute('welcome', ['email'], 'to@example.test');
    } catch (DeliveryFailedException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(DeliveryFailedException::class);
    expect($caught?->action)->toBe('welcome');
    expect($caught?->failedMethods)->toBe(['email']);
    expect($caught?->getPrevious())->toBeInstanceOf(RuntimeException::class);
});

it('attempts every requested method before failing', function (): void {
    $working = new RecordingDeliveryService();
    app()->instance(RecordingDeliveryService::class, $working);

    $action = new DeliveryAction([
        'email' => ['service' => FailingDeliveryService::class, 'factories' => ['welcome' => StubPayloadFactory::class]],
        'sms' => ['service' => RecordingDeliveryService::class, 'factories' => ['welcome' => StubPayloadFactory::class]],
    ]);

    expect(fn(): array => $action->execute('welcome', ['email', 'sms'], 'to@example.test'))
        ->toThrow(DeliveryFailedException::class);

    // The working method was still attempted even though the earlier one failed.
    expect($working->sent)->toBe(1);
});
