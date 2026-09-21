<?php

declare(strict_types=1);

namespace Rominas\Catalog\NomineeSubmission\Support;

use Illuminate\Support\Str;

/**
 * Single source of the name-normalization rule shared by free-text capture (the dedup key) and match
 * suggestions (comparison against a Catalog row's slug). Slugging casefolds, trims, collapses whitespace
 * and strips punctuation, so "Taylor Swift", "taylor swift" and " Taylor  Swift! " all collapse to one key.
 */
class NomineeNameNormalizer
{
    public static function normalize(string $name): string
    {
        return Str::slug($name);
    }
}
