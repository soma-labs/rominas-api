<?php

declare(strict_types=1);

namespace Rominas\Notifications\Actions;

use Illuminate\Support\Facades\Notification;
use Rominas\Notifications\Notifications\AdminNotification;
use Rominas\Users\Model\User;

/**
 * Sends an admin notification to every user who holds the given permission — the people who can act
 * on it. The check is the same `can()` the policies use, so wildcard permissions and the super-admin
 * bypass count exactly as they do for access. Returns the number of recipients.
 */
class NotifyAdminsWithPermissionAction
{
    public function execute(string $permission, AdminNotification $notification): int
    {
        $recipients = User::query()
            ->with(['roles.permissions', 'permissions'])
            ->get()
            ->filter(static fn(User $user): bool => $user->can($permission))
            ->values();

        Notification::send($recipients, $notification);

        return $recipients->count();
    }
}
