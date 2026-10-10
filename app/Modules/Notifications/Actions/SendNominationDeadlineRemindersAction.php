<?php

declare(strict_types=1);

namespace Rominas\Notifications\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Rominas\Academy\Member\Model\Member;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
use Rominas\Notifications\Enums\NotificationType;
use Rominas\Notifications\Notifications\NominationDeadlineReminder;

/**
 * Reminds academy members who have not submitted their nominations that the deadline is near. Meant to
 * run once a day: it sends only when the number of calendar days left (in
 * `config('notifications.timezone')`) is one of `config('notifications.nomination_reminder_days')`.
 * A member who already got the reminder for that day count is skipped, so a second run sends nothing
 * new. Returns the number of members reminded.
 */
class SendNominationDeadlineRemindersAction
{
    public function execute(): int
    {
        $edition = Edition::query()->filterByStatus(EditionStatus::NominationsOpen)->first();

        if ($edition === null) {
            return 0;
        }

        $timezone = (string) config('notifications.timezone');
        $deadline = $edition->nominations_end_at->toImmutable()->timezone($timezone);

        $daysLeft = (int) CarbonImmutable::now($timezone)->startOfDay()->diffInDays($deadline->startOfDay());

        /** @var list<int> $reminderDays */
        $reminderDays = config('notifications.nomination_reminder_days', []);

        if (! in_array($daysLeft, $reminderDays, true)) {
            return 0;
        }

        $alreadyReminded = DatabaseNotification::query()
            ->where('type', '=', NotificationType::NominationDeadlineReminder->value)
            ->where('notifiable_type', '=', (new Member())->getMorphClass())
            ->where('data->edition_id', '=', $edition->id)
            ->where('data->days_before', '=', $daysLeft)
            ->select('notifiable_id');

        $notification = new NominationDeadlineReminder(
            editionId: $edition->id,
            editionName: $edition->name,
            daysBefore: $daysLeft,
            deadline: $deadline->format('d.m.Y, H:i'),
        );

        $reminded = 0;

        Member::query()
            ->invitedOrActive()
            ->withoutSubmittedNominationFor($edition)
            ->whereNotIn('id', $alreadyReminded)
            ->chunkById(200, static function (Collection $members) use ($notification, &$reminded): void {
                Notification::send($members, $notification);
                $reminded += $members->count();
            });

        return $reminded;
    }
}
