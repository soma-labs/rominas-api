<?php

declare(strict_types=1);

namespace Rominas\Reporting\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Rominas\Editions\Model\Edition;
use Rominas\Reporting\DataTransferObjects\ReportParameters;
use Rominas\Reporting\Requests\RunReportRequest;

/**
 * Builds {@see ReportParameters} from a validated {@see RunReportRequest}: resolves the target edition
 * (an explicit `edition_id`, otherwise the single active edition) and the optional date window. Fails
 * validation when no edition is given and none is active, so a report always has a concrete edition.
 */
class ReportParametersFactory
{
    public static function fromRequest(RunReportRequest $request): ReportParameters
    {
        $validated = $request->validated();

        $edition = isset($validated['edition_id'])
            ? Edition::query()->findOrFail($validated['edition_id'])
            : Edition::query()->active()->first();

        if ($edition === null) {
            throw ValidationException::withMessages([
                'edition_id' => 'No active edition; specify an edition_id.',
            ]);
        }

        return new ReportParameters(
            edition: $edition,
            from: isset($validated['from']) ? CarbonImmutable::parse($validated['from']) : null,
            to: isset($validated['to']) ? self::parseTo($validated['to']) : null,
        );
    }

    private static function parseTo(string $to): CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) === 1) {
            return CarbonImmutable::parse($to)->endOfDay();
        }

        return CarbonImmutable::parse($to);
    }
}
