<?php

declare(strict_types=1);

namespace Rominas\Delivery\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Rominas\Delivery\Actions\DeliveryAction;
use Rominas\Delivery\Exceptions\DeliveryFailedException;
use Rominas\Delivery\SMTP\SmtpAcademyInvitationEmailPayloadFactory;
use Rominas\Notifications\Channels\DeliveryChannel;
use Rominas\Notifications\Notifications\DailyStatistics;
use Rominas\Notifications\Notifications\MemberJoined;
use Rominas\Notifications\Notifications\MemberProposalNeedsReview;
use Rominas\Notifications\Notifications\NewFraudAlerts;
use Rominas\Notifications\Notifications\NominationDeadlineReminder;
use Rominas\Notifications\Notifications\NominationsOpened;
use InvalidArgumentException;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Sends one e-mail template (or all of them) with sample data to an address given on the command line,
 * to check how the e-mails look and that the mail transport works. Sends right away, without the queue,
 * and writes nothing to the database: links carry a placeholder token and do not sign anyone in.
 */
class SendTestEmailCommand extends Command implements PromptsForMissingInput
{
    private const string ALL = 'all';

    private const string TOKEN = 'test-token';

    private const string EDITION_NAME = 'Romias 2026';

    protected $signature = 'rominas:test-mail
        {email : The address that receives the e-mail}
        {template : The template to send, or "all"}';

    protected $description = 'Send an e-mail template with sample data to an address, to check it by hand';

    public function handle(DeliveryAction $delivery, DeliveryChannel $channel): int
    {
        $email = (string) $this->argument('email');
        $template = (string) $this->argument('template');

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->error("Not a valid e-mail address: {$email}");

            return self::FAILURE;
        }

        $templates = self::templates();

        if ($template !== self::ALL && ! array_key_exists($template, $templates)) {
            $this->error("Unknown template: {$template}. Choose one of: " . implode(', ', array_keys($templates)) . ', ' . self::ALL . '.');

            return self::FAILURE;
        }

        $keys = $template === self::ALL ? array_keys($templates) : [$template];

        foreach ($keys as $key) {
            try {
                $this->send($key, $email, $delivery, $channel);
            } catch (DeliveryFailedException $e) {
                $this->error("Failed: {$templates[$key]}");
                $this->line($e->getPrevious()?->getMessage() ?? $e->getMessage());

                return self::FAILURE;
            }

            $this->info("Sent: {$templates[$key]}");
        }

        $this->line('Links in these e-mails carry a placeholder token and do not sign anyone in.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, callable(): mixed>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'email' => fn(): string => text(
                label: 'Which address should receive the e-mail?',
                required: true,
                validate: ['email' => ['email']],
            ),
            'template' => fn(): string => (string) select(
                label: 'Which template?',
                options: [...self::templates(), self::ALL => 'All of the above'],
                scroll: 12,
            ),
        ];
    }

    /**
     * @return array<string, string> template key => label
     */
    private static function templates(): array
    {
        return [
            'academy-invitation' => 'Academy invitation (member added by an admin)',
            'academy-invitation-proposed' => 'Academy invitation (member from a proposal)',
            'academy-login' => 'Academy login link',
            'voting-link' => 'Voting link',
            'nominations-opened' => 'Nominations opened',
            'nomination-reminder-3' => 'Nomination deadline reminder, 3 days before',
            'nomination-reminder-1' => 'Nomination deadline reminder, 1 day before',
            'admin-member-proposal' => 'Admin: new member proposal',
            'admin-member-joined' => 'Admin: member joined',
            'admin-fraud-alerts' => 'Admin: new fraud alerts',
            'admin-daily-statistics' => 'Admin: daily statistics',
        ];
    }

    private function send(string $template, string $email, DeliveryAction $delivery, DeliveryChannel $channel): void
    {
        match ($template) {
            'academy-invitation' => $delivery->execute('academy-invitation-email', ['email'], $email, self::TOKEN),
            'academy-invitation-proposed' => $delivery->execute(
                'notification-email',
                ['email'],
                $email,
                SmtpAcademyInvitationEmailPayloadFactory::SUBJECT,
                'emails.academy.invitation',
                [
                    'name' => 'ana.popescu',
                    'url' => rtrim((string) config('services.frontend.academy_url'), '/') . '/invitatie?token=' . self::TOKEN . '&email=' . rawurlencode($email),
                    'proposerName' => 'Ion Ionescu',
                ],
            ),
            'academy-login' => $delivery->execute('academy-magic-link-email', ['email'], $email, self::TOKEN),
            'voting-link' => $delivery->execute('voting-link-email', ['email'], $email, self::TOKEN),
            default => $channel->send(Notification::route('mail', $email), $this->notification($template)),
        };
    }

    private function notification(string $template): BaseNotification
    {
        $deadline = CarbonImmutable::now((string) config('notifications.timezone'))->addDays(3)->format('d.m.Y, H:i');

        return match ($template) {
            'nominations-opened' => new NominationsOpened(1, self::EDITION_NAME, $deadline),
            'nomination-reminder-3' => new NominationDeadlineReminder(1, self::EDITION_NAME, 3, $deadline),
            'nomination-reminder-1' => new NominationDeadlineReminder(1, self::EDITION_NAME, 1, $deadline),
            'admin-member-proposal' => new MemberProposalNeedsReview(1, 'Ana Popescu', 'Ion Ionescu'),
            'admin-member-joined' => new MemberJoined(1, 'Ana Popescu'),
            'admin-fraud-alerts' => new NewFraudAlerts(1, self::EDITION_NAME, 3),
            'admin-daily-statistics' => new DailyStatistics(
                editionId: 1,
                editionName: self::EDITION_NAME,
                day: CarbonImmutable::now((string) config('notifications.timezone'))->subDay()->format('j M Y'),
                rows: [
                    ['metric' => 'Voting links issued', 'day' => 42, 'total' => 1250],
                    ['metric' => 'Ballots submitted', 'day' => 37, 'total' => 1104],
                    ['metric' => 'Valid votes', 'day' => 35, 'total' => 1089],
                    ['metric' => 'Cancelled votes', 'day' => 2, 'total' => 15],
                    ['metric' => 'Academy nominations submitted', 'day' => 3, 'total' => 58],
                    ['metric' => 'Academy nominations in draft', 'day' => null, 'total' => 9],
                ],
            ),
            default => throw new InvalidArgumentException("Unknown template [{$template}]."),
        };
    }
}
