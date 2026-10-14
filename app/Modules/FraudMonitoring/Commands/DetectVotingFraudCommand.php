<?php

declare(strict_types=1);

namespace Rominas\FraudMonitoring\Commands;

use Illuminate\Console\Command;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
use Rominas\FraudMonitoring\Actions\DetectVotingFraudAction;

/**
 * Scheduled fraud sweep: runs the detectors over each relevant edition and records/refreshes FraudAlerts.
 * By default it targets non-archived editions in the states where submitted ballots exist and still
 * matter (voting open through committee review); `--edition` restricts it to one edition by id.
 */
class DetectVotingFraudCommand extends Command
{
    protected $signature = 'rominas:detect-fraud {--edition= : Restrict detection to a single edition id}';

    protected $description = 'Scan submitted public ballots for suspicious activity and record fraud alerts';

    public function handle(DetectVotingFraudAction $action): int
    {
        $editions = $this->targetEditions();

        if ($editions->isEmpty()) {
            $this->info('No editions to scan.');

            return self::SUCCESS;
        }

        foreach ($editions as $edition) {
            $touched = $action->execute($edition);
            $this->info("Edition {$edition->id} ({$edition->slug}): {$touched} alert(s) detected/refreshed.");
        }

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Edition>
     */
    private function targetEditions(): \Illuminate\Support\Collection
    {
        $editionId = $this->option('edition');

        if ($editionId !== null) {
            return Edition::query()->whereKey($editionId)->get();
        }

        return Edition::query()
            ->whereIn('status', [
                EditionStatus::VotingOpen->value,
                EditionStatus::VotingClosed->value,
                EditionStatus::CommitteeReview->value,
            ])
            ->get();
    }
}
