<?php

declare(strict_types=1);

namespace Rominas\Delivery\Actions;

use Rominas\Delivery\Exceptions\DeliveryFailedException;
use Rominas\Delivery\PayloadFactoryInterface;
use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

readonly class DeliveryAction
{
    /**
     * @param  array<string, array{service: class-string, factories?: array<string, class-string>}>  $availableDeliveryMethods
     */
    public function __construct(private array $availableDeliveryMethods) {}

    /**
     * @param  list<string>  $deliveryMethods
     * @return array<string, string>
     */
    public function execute(string $action, array $deliveryMethods, mixed ...$payloadFactoryParams): array
    {
        $deliveryStatuses = [];
        $failedMethods = [];
        $lastFailure = null;

        foreach ($this->availableDeliveryMethods as $deliveryMethodName => $deliveryMethodConfig) {
            if (! in_array($deliveryMethodName, $deliveryMethods, true)) {
                continue;
            }

            try {
                $deliveryService = app()->make($deliveryMethodConfig['service']);

                $factories = $deliveryMethodConfig['factories'] ?? [];
                $payloadFactoryClass = $factories[$action]
                    ?? throw new InvalidArgumentException("No payload factory for action [{$action}]");

                /** @var PayloadFactoryInterface $payloadFactory */
                $payloadFactory = new $payloadFactoryClass(...$payloadFactoryParams);
                $payload = $payloadFactory->create();
                $deliveryService->send($payload);

                $deliveryStatuses[$deliveryMethodName] = 'success';
            } catch (Exception $e) {
                Log::error('Error delivering message', [
                    'method' => $deliveryMethodName,
                    'action' => $action,
                    'params' => $payloadFactoryParams,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $deliveryStatuses[$deliveryMethodName] = 'failed';
                $failedMethods[] = $deliveryMethodName;
                $lastFailure = $e;
            }
        }

        // Every requested method is attempted (and logged) before we raise, so a multi-method send
        // still tries them all; but any failure must surface so the queued job is marked failed
        // rather than completing silently after a swallowed delivery error.
        if ($failedMethods !== []) {
            throw new DeliveryFailedException($action, $failedMethods, $lastFailure);
        }

        return $deliveryStatuses;
    }
}
