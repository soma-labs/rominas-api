<?php

declare(strict_types=1);

namespace Rominas\Delivery\Actions;

use Rominas\Delivery\Exceptions\DeliveryFailedException;
use Rominas\Delivery\PayloadFactoryInterface;
use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use ReflectionClass;
use SensitiveParameter;

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

            $payloadFactoryClass = null;

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
                Log::channel('delivery')->error('Error delivering message', [
                    'method' => $deliveryMethodName,
                    'action' => $action,
                    'params' => $this->loggableParams($payloadFactoryClass, $payloadFactoryParams),
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

    /**
     * The params as they may be written to the log: every argument the payload factory's constructor
     * marks with #[SensitiveParameter] (a link token) is replaced. Without a known factory nothing says
     * which argument is a token, so none of them is logged.
     *
     * @param  class-string|null  $payloadFactoryClass
     * @param  array<int|string, mixed>  $payloadFactoryParams
     * @return array<int|string, mixed>|string
     */
    private function loggableParams(?string $payloadFactoryClass, array $payloadFactoryParams): array|string
    {
        if ($payloadFactoryClass === null) {
            return '[not logged: no payload factory]';
        }

        $constructor = (new ReflectionClass($payloadFactoryClass))->getConstructor();

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            if ($parameter->getAttributes(SensitiveParameter::class) === []) {
                continue;
            }

            foreach ([$parameter->getPosition(), $parameter->getName()] as $key) {
                if (array_key_exists($key, $payloadFactoryParams)) {
                    $payloadFactoryParams[$key] = '[hidden]';
                }
            }
        }

        return $payloadFactoryParams;
    }
}
