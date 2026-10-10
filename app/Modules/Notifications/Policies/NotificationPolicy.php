<?php

declare(strict_types=1);

namespace Rominas\Notifications\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Notifications\DatabaseNotification;
use Rominas\Users\Model\User;

/**
 * Every admin user has a notification list, and it is theirs alone: a user reads and marks only the
 * notifications addressed to them. Registered for Laravel's DatabaseNotification in AuthServiceProvider.
 */
class NotificationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function update(User $user, DatabaseNotification $notification): bool
    {
        return $notification->notifiable_type === $user->getMorphClass()
            && (int) $notification->notifiable_id === $user->id;
    }
}
