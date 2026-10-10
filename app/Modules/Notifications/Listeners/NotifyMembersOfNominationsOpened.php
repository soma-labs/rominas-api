<?php

declare(strict_types=1);

namespace Rominas\Notifications\Listeners;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use Rominas\Academy\Member\Model\Member;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Events\EditionTransitioned;
use Rominas\Notifications\Notifications\NominationsOpened;

/**
 * E-mails every invited or active academy member when an edition's nominations open. Wired in
 * EventServiceProvider.
 */
class NotifyMembersOfNominationsOpened
{
    public function handle(EditionTransitioned $event): void
    {
        if ($event->to !== EditionStatus::NominationsOpen) {
            return;
        }

        $edition = $event->edition;

        $notification = new NominationsOpened(
            editionId: $edition->id,
            editionName: $edition->name,
            deadline: $edition->nominations_end_at
                ->timezone((string) config('notifications.timezone'))
                ->format('d.m.Y, H:i'),
        );

        Member::query()
            ->invitedOrActive()
            ->chunkById(200, static function (Collection $members) use ($notification): void {
                Notification::send($members, $notification);
            });
    }
}
