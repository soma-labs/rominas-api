<?php

declare(strict_types=1);

namespace Rominas\Notifications\Actions;

use Carbon\CarbonImmutable;
use Rominas\Editions\Model\Edition;
use Rominas\Notifications\Notifications\DailyStatistics;
use Rominas\Reporting\DataTransferObjects\ReportParameters;
use Rominas\Reporting\Reports\EditionSummaryReport;

/**
 * Sends the previous day's statistics of the edition in progress to every user with the `reporting`
 * permission. The numbers are the Edition Summary report run twice: over the previous day and over
 * the whole edition. "The previous day" is a calendar day in `config('notifications.timezone')`.
 * Returns the number of recipients — 0 when no edition is in progress, in which case nothing is sent.
 */
class SendDailyStatisticsAction
{
    /**
     * Edition Summary metrics that are a count at the moment the report runs, with no per-day value.
     *
     * @var list<string>
     */
    private const POINT_IN_TIME_METRICS = [
        'Academy nominations in draft',
    ];

    public function __construct(
        private readonly EditionSummaryReport $report,
        private readonly NotifyAdminsWithPermissionAction $notifyAdmins,
    ) {}

    public function execute(): int
    {
        $edition = Edition::query()->inProgress()->first();

        if ($edition === null) {
            return 0;
        }

        $previousDay = CarbonImmutable::now((string) config('notifications.timezone'))->subDay();

        $dayValues = [];

        $dayRows = $this->report->rows(new ReportParameters(
            edition: $edition,
            from: $previousDay->startOfDay()->utc(),
            to: $previousDay->endOfDay()->utc(),
        ));

        foreach ($dayRows as $row) {
            $dayValues[(string) $row['metric']] = (int) $row['value'];
        }

        $rows = [];

        foreach ($this->report->rows(new ReportParameters(edition: $edition)) as $row) {
            $metric = (string) $row['metric'];

            $rows[] = [
                'metric' => $metric,
                'day' => in_array($metric, self::POINT_IN_TIME_METRICS, true) ? null : ($dayValues[$metric] ?? 0),
                'total' => (int) $row['value'],
            ];
        }

        return $this->notifyAdmins->execute('reporting', new DailyStatistics(
            editionId: $edition->id,
            editionName: $edition->name,
            day: $previousDay->format('j M Y'),
            rows: $rows,
        ));
    }
}
