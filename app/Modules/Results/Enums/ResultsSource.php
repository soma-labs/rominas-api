<?php

declare(strict_types=1);

namespace Rominas\Results\Enums;

/**
 * Where an edition's results were read from: computed live during the review window, or the frozen
 * snapshot written when results are published.
 */
enum ResultsSource: string
{
    case Live = 'live';
    case Snapshot = 'snapshot';
}
