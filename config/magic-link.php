<?php

declare(strict_types=1);

/*
 * Magic-link lifetimes. A link's expiry is stored on its row (`magic_link_tokens.expires_at`) when it is
 * issued, so changing a value here only affects links issued afterwards.
 */
return [

    /*
    | Lifetime (minutes) of a link whose e-mail type has no entry below — the returning-login links.
    */
    'default_ttl_minutes' => (int) env('MAGIC_LINK_TTL_MINUTES', 15),

    /*
    | Per-e-mail-type lifetimes (minutes), keyed by the Delivery action key the SendMagicLinkJob carries
    | (see config/delivery.php). An invitation is usually opened hours after it is sent, so it lives longer.
    | The e-mail templates (resources/views/emails/academy/) state these lifetimes in their own wording —
    | update them when a value changes.
    */
    'ttl_minutes' => [
        'academy-invitation-email' => (int) env('MAGIC_LINK_INVITATION_TTL_MINUTES', 48 * 60),
    ],

];
