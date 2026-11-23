<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Rominas\Academy\Member\Actions\QueueCalendarUpdateEmailsAction;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;

/**
 * One-time announcement to every member. Without `--send` it only prints who would receive it. The
 * cache key is taken before anything is queued, so a second `--send` cannot e-mail members twice.
 */
class SendCalendarUpdateCommand extends Command
{
    private const string QUEUED_CACHE_KEY = 'romias:calendar-update-queued';

    protected $signature = 'romias:send-calendar-update
        {--send : Queue the e-mails; without it, only print the recipient counts}';

    protected $description = 'Send the one-time calendar update e-mail to every academy member';

    public function handle(QueueCalendarUpdateEmailsAction $action): int
    {
        if (! $this->option('send')) {
            $this->printRecipientCounts();
            $this->line('Dry run: nothing was sent. Add --send to queue the e-mails.');

            return self::SUCCESS;
        }

        if (! Cache::add(self::QUEUED_CACHE_KEY, now()->toIso8601String())) {
            $this->error('The calendar update was already queued. Nothing was sent.');

            return self::FAILURE;
        }

        $queuedEmailCount = $action->execute();

        $this->info("Calendar update queued for {$queuedEmailCount} member(s).");

        return self::SUCCESS;
    }

    private function printRecipientCounts(): void
    {
        $countsByStatus = Member::query()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $this->table(
            ['Status', 'Members'],
            array_map(
                fn(MemberStatus $status): array => [$status->label(), (int) ($countsByStatus[$status->value] ?? 0)],
                MemberStatus::cases(),
            ),
        );

        $this->info('Total recipients: ' . $countsByStatus->sum());
    }
}
