<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Voter pseudonymization pepper
    |--------------------------------------------------------------------------
    |
    | Stable secret keying the HMAC hashes of voter identifiers (email_hash,
    | ip_hash) stored by the Voting module. Personal data is never persisted in
    | plaintext (GDPR); the pepper makes the hashes non-reversible without it.
    |
    | It MUST be set and MUST NOT be rotated once ballots exist — rotating it
    | breaks the one-link-per-email lookup (every existing email_hash would stop
    | matching). Falls back to APP_KEY so non-production works out of the box;
    | set a dedicated, stable VOTING_PEPPER in production.
    |
    */

    'pepper' => env('VOTING_PEPPER', env('APP_KEY', '')),

    /*
    |--------------------------------------------------------------------------
    | E-mail validation decisions
    |--------------------------------------------------------------------------
    |
    | What a voting-link request does with each ZeroBounce result, keyed by
    | status, then sub-status ('' = no sub-status):
    |
    |   accept — the link is sent;
    |   flag   — the link is sent and the ballot is marked for fraud review
    |            ("accepted with a note");
    |   reject — no ballot and no link; the voter gets the same generic answer
    |            as everyone else and is never told why.
    |
    | A sub-status missing here takes its status's '' row; an unknown status
    | is flagged. Source: docs/zero-bounce-scores.csv in the parent folder.
    |
    */

    'email_validation' => [

        'decisions' => [
            'valid' => [
                '' => 'accept',
                'alias_address' => 'accept',
                'leading_period_removed' => 'accept',
                'alternate' => 'accept',
                'gold' => 'accept',
                'role_based_accept_all' => 'flag',
                'accept_all' => 'flag',
                'ai_agent_mailbox' => 'flag',
            ],
            'invalid' => [
                '' => 'reject',
            ],
            'catch-all' => [
                '' => 'flag',
                'ai_agent_mailbox' => 'reject',
            ],
            'spamtrap' => [
                '' => 'reject',
            ],
            'abuse' => [
                '' => 'flag',
            ],
            'do_not_mail' => [
                '' => 'flag',
                'role_based' => 'flag',
                'disposable' => 'reject',
                'role_based_catch_all' => 'reject',
                'mx_forward' => 'flag',
                'global_suppression' => 'flag',
                'possible_trap' => 'reject',
                'toxic' => 'reject',
            ],
            'unknown' => [
                '' => 'flag',
            ],
        ],

        // How long a rejected address (its hash, never the address) is remembered, so repeat
        // attempts are refused without spending another ZeroBounce credit.
        'rejection_cache_minutes' => (int) env('VOTING_EMAIL_REJECTION_CACHE_MINUTES', 60),

    ],

    /*
    |--------------------------------------------------------------------------
    | Voting queues
    |--------------------------------------------------------------------------
    |
    | A link request only queues work; the voting workers (laravel-worker-voting
    | in docker/supervisor/supervisord.conf) do it:
    |
    |   validation — IssueVotingLinkJob: e-mail validation, the ballot, and
    |                queueing the link e-mail;
    |   mail       — SendVotingLinkJob: sending the link e-mail.
    |
    | The workers take `mail` first, so a voter whose address passed gets the
    | e-mail before new checks start. Rename a queue only together with the
    | worker's --queue list.
    |
    */

    'queues' => [
        'validation' => env('VOTING_VALIDATION_QUEUE', 'voting-validation'),
        'mail' => env('VOTING_MAIL_QUEUE', 'voting-mail'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue backlog alert
    |--------------------------------------------------------------------------
    |
    | The scheduler checks both voting queues every minute; when one holds more
    | jobs than this, admins with the `reporting` permission are notified (at
    | most once per queue every `queue_alert_cooldown_minutes`). Links in the
    | queue are delayed, not lost.
    |
    */

    'queue_alert_threshold' => (int) env('VOTING_QUEUE_ALERT_THRESHOLD', 500),

    'queue_alert_cooldown_minutes' => (int) env('VOTING_QUEUE_ALERT_COOLDOWN_MINUTES', 30),

];
