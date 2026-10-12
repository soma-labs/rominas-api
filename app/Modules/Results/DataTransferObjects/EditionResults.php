<?php

declare(strict_types=1);

namespace Rominas\Results\DataTransferObjects;

use Carbon\CarbonInterface;
use Rominas\Editions\Model\Edition;
use Rominas\Results\Enums\ResultsSource;
use Rominas\Scoring\DataTransferObjects\EditionScore;
use Rominas\SpecialAwards\DataTransferObjects\SpecialCategoryResult;

/**
 * An edition's results together with where they came from: the Scoring tree of the ranked categories,
 * the results of the special categories, the edition they belong to, whether they were computed live or
 * read from the frozen snapshot, and when they were published (snapshot only).
 */
final class EditionResults
{
    /**
     * @param  list<SpecialCategoryResult>  $specialCategories
     */
    public function __construct(
        public readonly Edition $edition,
        public readonly EditionScore $score,
        public readonly array $specialCategories,
        public readonly ResultsSource $source,
        public readonly ?CarbonInterface $publishedAt,
    ) {}
}
