<?php

declare(strict_types=1);

namespace Rominas\Notifications\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Notifications\DatabaseNotification;
use Rominas\Notifications\Requests\ListNotificationsRequest;
use Rominas\Notifications\Resources\NotificationResource;
use Rominas\Users\Model\User;

use function response;

/**
 * The signed-in admin's own notifications: the list, the unread count, and marking as read. There is
 * no endpoint to create or delete one — notifications are produced by the API itself.
 */
class NotificationsController
{
    public const PER_PAGE = 20;

    /**
     * The user's notifications, newest first; `unread=1` keeps only the unread ones.
     */
    public function index(ListNotificationsRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $query = $request->boolean('unread') ? $user->unreadNotifications() : $user->notifications();

        return NotificationResource::collection($query->paginate(self::PER_PAGE));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['count' => $user->unreadNotifications()->count()]);
    }

    public function markAsRead(DatabaseNotification $notification): NotificationResource
    {
        $notification->markAsRead();

        return new NotificationResource($notification);
    }

    /**
     * Marks every unread notification of the user as read and returns how many there were.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $count = $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['count' => $count]);
    }
}
