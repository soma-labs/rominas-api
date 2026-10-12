<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Entry\Factories;

use Rominas\SpecialAwards\Entry\DataTransferObjects\SpecialEntryData;
use Rominas\SpecialAwards\Entry\Requests\SaveSpecialEntryRequest;

class SpecialEntryDataFactory
{
    public static function fromRequest(SaveSpecialEntryRequest $request): SpecialEntryData
    {
        $validated = $request->validated();
        $description = trim((string) ($validated['description'] ?? ''));

        return new SpecialEntryData(
            title: trim((string) $validated['title']),
            description: $description === '' ? null : $description,
        );
    }
}
