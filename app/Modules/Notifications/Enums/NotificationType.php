<?php

declare(strict_types=1);

namespace Rominas\Notifications\Enums;

/**
 * The stable slug of each notification, stored in `notifications.type` (instead of the PHP class name)
 * and returned by the API, so stored rows and clients survive a class rename.
 */
enum NotificationType: string
{
    case MemberProposalNeedsReview = 'member_proposal_needs_review';
    case MemberJoined = 'member_joined';
    case NewFraudAlerts = 'new_fraud_alerts';
    case DailyStatistics = 'daily_statistics';
    case NominationsOpened = 'nominations_opened';
    case NominationDeadlineReminder = 'nomination_deadline_reminder';
}
