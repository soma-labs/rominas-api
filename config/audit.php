<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Ignored routes
    |--------------------------------------------------------------------------
    |
    | Full route names the audit middleware never records. The audit-log read
    | endpoints are here so reading the trail does not spam it.
    |
    */

    'ignore' => [
        'api.admin.audit-logs.index',
        'api.admin.audit-logs.view',
        // Marking one's own notifications as read is not a sensitive action.
        'api.admin.notifications.read',
        'api.admin.notifications.read-all',
    ],

    /*
    |--------------------------------------------------------------------------
    | Explicitly audited routes (opt-in, any method)
    |--------------------------------------------------------------------------
    |
    | Full route names outside the /admin group that must be audited — chiefly
    | authentication and account lifecycle events. Admin routes do not need
    | listing here: they are covered opt-out (see below).
    |
    */

    'audited' => [
        // Admin session lifecycle.
        'api.authenticate',
        'api.logout',
        // Academy member session + account lifecycle.
        'api.academy.auth.magic.request',
        'api.academy.auth.magic.verify',
        'api.academy.logout',
        'api.academy.proposals.withdraw',
        // A member's actions in the special categories: they decide those awards.
        'api.academy.special-awards.proposal.create',
        'api.academy.special-awards.proposal.withdraw',
        'api.academy.special-awards.vote.cast',
        'api.academy.special-awards.vote.withdraw',
    ],

    /*
    |--------------------------------------------------------------------------
    | Audited reads (admin group)
    |--------------------------------------------------------------------------
    |
    | Under /admin, coverage is opt-out for state-changing requests (every
    | POST/PUT/PATCH/DELETE is logged unless ignored). Reads (GET) are noisy, so
    | only the sensitive ones listed here are recorded — chiefly viewing/exporting
    | final results (ecosystem CLAUDE.md §6).
    |
    */

    'audited_reads' => [
        'api.admin.editions.results.show',
        'api.admin.editions.results.export',
    ],

    /*
    |--------------------------------------------------------------------------
    | Actor resolution
    |--------------------------------------------------------------------------
    |
    | `causer_types` maps an authenticated model to the stable alias stored in
    | `causer_type`. For routes where the actor is not yet authenticated during the
    | request (a login), `actor_from_response` recovers the id from the success
    | response body — {type, field} keyed by route name.
    |
    */

    'causer_types' => [
        \Rominas\Users\Model\User::class => 'user',
        \Rominas\Academy\Member\Model\Member::class => 'member',
    ],

    'actor_from_response' => [
        'api.authenticate' => ['type' => 'user', 'field' => 'userId'],
        'api.academy.auth.magic.verify' => ['type' => 'member', 'field' => 'memberId'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redacted payload keys
    |--------------------------------------------------------------------------
    |
    | Glob patterns (Str::is, case-insensitive) matched against request-body keys
    | at any depth. Matching values are replaced with "[redacted]" before storage.
    | Emails are redacted too — the subject reference already identifies the person
    | (GDPR §6); credentials and tokens must never be stored.
    |
    */

    'redact' => [
        'password',
        'password_confirmation',
        '*token*',
        '*secret*',
        'otp',
        '*email*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Email hashing on auth routes
    |--------------------------------------------------------------------------
    |
    | On these routes the attempted email is the only identifier of an otherwise
    | anonymous actor (a failed admin login, a magic-link request). Instead of
    | redacting it we store a pseudonymized HMAC hash (`email_hash`) — GDPR-aligned
    | (§6), non-reversible, but still correlatable across attempts. Keyed by
    | `pepper` below.
    |
    */

    'hash_emails_on' => [
        'api.authenticate',
        'api.academy.auth.magic.request',
        'api.academy.auth.magic.verify',
    ],

    // Stable secret keying the audit email hashes. Falls back to APP_KEY for non-production; set a
    // dedicated AUDIT_PEPPER in production. Rotating it only breaks correlation of past auth attempts.
    'pepper' => env('AUDIT_PEPPER', env('APP_KEY', '')),

    // Hard cap on the serialized `context` size; the request payload is dropped if exceeded.
    'max_context_bytes' => 16384,
];
