<?php

declare(strict_types=1);

namespace Rominas\Results\DataTransferObjects;

use Carbon\CarbonInterface;
use Rominas\Editions\Model\Edition;
use Rominas\Results\Enums\ResultsSource;
use Rominas\Scoring\DataTransferObjects\EditionScore;

/**
 * An edition's results together with where they came from: the Scoring tree, the edition it belongs to,
 * whether it was computed live or read from the frozen snapshot, and when it was published (snapshot only).
 */
final class EditionResults
{
    public function __construct(
        public readonly Edition $edition,
        public readonly EditionScore $score,
        public readonly ResultsSource $source,
        public readonly ?CarbonInterface $publishedAt,
    ) {}
}
