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

    /**
     * The normalized name split into its distinct word tokens. `Str::slug` already casefolds and joins
     * words with "-", so the slug segments are the tokens: "Delia Matache" and "Matache Delia" both yield
     * ["delia", "matache"]. This is what lets match scoring ignore word order.
     *
     * @return list<string>
     */
    public static function tokens(string $name): array
    {
        $slug = self::normalize($name);

        return $slug === '' ? [] : explode('-', $slug);
    }
}
