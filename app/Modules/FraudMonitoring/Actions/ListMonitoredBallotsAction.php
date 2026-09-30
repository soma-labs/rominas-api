<?php

declare(strict_types=1);

namespace Rominas\FraudMonitoring\Actions;

use Illuminate\Pagination\LengthAwarePaginator;
use Rominas\Editions\Model\Edition;
use Rominas\FraudMonitoring\DataTransferObjects\MonitoredBallotFilters;
use Rominas\FraudMonitoring\QueryBuilders\InvalidationBatchQueryBuilder;
use Rominas\Voting\Model\Ballot;

/**
 * The fraud-review ballot list for an edition: every submitted ballot, decorated with a basic fraud
 * signal — how many of the edition's submitted ballots share its `ip_hash` — and its current invalid
 * state, optionally narrowed by `MonitoredBallotFilters`. The shared-IP count is always edition-wide, never
 * affected by the filters. No plaintext PII is exposed; the resource surfaces hashes only.
 */
class ListMonitoredBallotsAction
{
    /**
     * @return LengthAwarePaginator<int, Ballot>
     */
    public function execute(
        Edition $edition,
        MonitoredBallotFilters $filters = new MonitoredBallotFilters(),
        int $perPage = InvalidationBatchQueryBuilder::PER_PAGE,
    ): LengthAwarePaginator {
        // How many submitted ballots share each ip_hash across the whole edition, so per-page slicing
        // does not distort the duplicate count.
        $sharedByIpHash = Ballot::query()
            ->forEdition($edition)
            ->submitted()
            ->whereNotNull('ip_hash')
            ->get(['ip_hash'])
            ->countBy('ip_hash');

        /** @var LengthAwarePaginator<int, Ballot> $ballots */
        $ballots = Ballot::query()
            ->forEdition($edition)
            ->submitted()
            ->when($filters->ipHash !== null, fn($query) => $query->byIpHash((string) $filters->ipHash))
            ->when($filters->invalidated === true, fn($query) => $query->invalidated())
            ->when($filters->invalidated === false, fn($query) => $query->valid())
            ->with('invalidationBatch')
            ->orderByDesc('id')
            ->paginate($perPage);

        $ballots->getCollection()->each(function (Ballot $ballot) use ($sharedByIpHash): void {
            $count = $ballot->ip_hash === null ? 1 : (int) $sharedByIpHash->get($ballot->ip_hash, 1);
            $ballot->setAttribute('ip_hash_shared_count', $count);
        });

        return $ballots;
    }
}
