<?php

declare(strict_types=1);

namespace Rominas\FraudMonitoring\DataTransferObjects;

/**
 * Optional narrowing of the fraud-review ballot list: only ballots with this `ip_hash`, and/or only
 * cancelled (true) or still-valid (false) ballots. Null means "no filter".
 */
final class MonitoredBallotFilters
{
    public function __construct(
        public readonly ?string $ipHash = null,
        public readonly ?bool $invalidated = null,
    ) {}
}
