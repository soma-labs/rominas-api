<?php

declare(strict_types=1);

namespace Rominas\FraudMonitoring\Actions;

use Illuminate\Support\Collection;
use Rominas\Editions\Model\Edition;
use Rominas\FraudMonitoring\Enums\FraudAlertStatus;
use Rominas\FraudMonitoring\Model\FraudAlert;
use Rominas\Voting\Model\Ballot;

/**
 * The editions a fraud monitor can open (public voting started onward), newest first, each decorated with
 * its submitted / cancelled ballot counts and pending-alert count. The counts are read with three grouped
 * queries rather than per edition.
 */
class ListFraudMonitoringEditionsAction
{
    /**
     * @return Collection<int, Edition>
     */
    public function execute(): Collection
    {
        $editions = Edition::query()->withPublicVotingStarted()->latest('starts_at')->get();
        $editionIds = $editions->modelKeys();

        $submitted = Ballot::query()->submitted()->whereIn('edition_id', $editionIds)
            ->selectRaw('edition_id, count(*) as total')->groupBy('edition_id')->pluck('total', 'edition_id');

        $cancelled = Ballot::query()->submitted()->invalidated()->whereIn('edition_id', $editionIds)
            ->selectRaw('edition_id, count(*) as total')->groupBy('edition_id')->pluck('total', 'edition_id');

        $pendingAlerts = FraudAlert::query()->where('status', FraudAlertStatus::Pending->value)
            ->whereIn('edition_id', $editionIds)
            ->selectRaw('edition_id, count(*) as total')->groupBy('edition_id')->pluck('total', 'edition_id');

        return $editions->each(function (Edition $edition) use ($submitted, $cancelled, $pendingAlerts): void {
            $edition->setAttribute('submitted_ballots_count', (int) $submitted->get($edition->id, 0));
            $edition->setAttribute('cancelled_ballots_count', (int) $cancelled->get($edition->id, 0));
            $edition->setAttribute('pending_alerts_count', (int) $pendingAlerts->get($edition->id, 0));
        });
    }
}
