<?php

declare(strict_types=1);

namespace Rominas\Delivery\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown by {@see \Rominas\Delivery\Actions\DeliveryAction} when one or more requested delivery
 * methods fail to send. Raising it lets the surrounding queued job fail (and retry) instead of
 * completing silently after a swallowed delivery error.
 */
class DeliveryFailedException extends RuntimeException
{
    /**
     * @param  non-empty-list<string>  $failedMethods
     */
    public function __construct(
        public readonly string $action,
        public readonly array $failedMethods,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Delivery of [%s] failed for method(s): %s.',
                $action,
                implode(', ', $failedMethods),
            ),
            previous: $previous,
        );
    }
}
