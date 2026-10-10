<?php

declare(strict_types=1);

namespace Rominas\Notifications\Notifications;

use Rominas\Notifications\Enums\NotificationType;

/**
 * An invited academy member activated their account with their first sign-in.
 */
class MemberJoined extends AdminNotification
{
    public function __construct(
        public readonly int $memberId,
        public readonly string $memberName,
    ) {
        $this->afterCommit();
    }

    public function type(): NotificationType
    {
        return NotificationType::MemberJoined;
    }

    public function title(): string
    {
        return "{$this->memberName} joined the academy";
    }

    public function body(): string
    {
        return "{$this->memberName} accepted the invitation and activated their account.";
    }

    public function url(): string
    {
        return "/members/{$this->memberId}";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [...parent::toArray($notifiable), 'member_id' => $this->memberId];
    }
}
