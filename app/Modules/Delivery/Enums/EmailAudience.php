<?php

declare(strict_types=1);

namespace Rominas\Delivery\Enums;

/**
 * Who an e-mail is for. Each audience has its own sender name (`delivery.sender_names`); the sender
 * address is `mail.from.address` for all of them.
 */
enum EmailAudience: string
{
    case Admin = 'admin';
    case Academy = 'academy';
    case Voting = 'voting';

    public function senderName(): string
    {
        return (string) config("delivery.sender_names.{$this->value}");
    }
}
