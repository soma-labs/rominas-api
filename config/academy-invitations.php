<?php

declare(strict_types=1);

/*
 * The academy invitation e-mail's templates. An admin picks one when sending invitations from the
 * members page (`POST /api/admin/members/invitations/send`); every other invitation (one member, "invite
 * all pending", an approved proposal) uses the default. Each view takes `name`, `url` and `proposerName`
 * and states the invitation link's lifetime (config/magic-link.php) in its own words. To add a template,
 * add its Blade file under resources/views/emails/academy/invitations/ and an entry here.
 */
return [

    // The template every invitation uses unless an admin picks another; listed first in the admin.
    'default' => 'default',

    'templates' => [
        'default' => [
            'label' => 'Default (Romanian)',
            'view' => 'emails.academy.invitations.default',
            'subject' => 'Invitație în Academia The Romanian Music Industry Awards',
        ],
        'unconfirmed' => [
            'label' => 'Unconfirmed (Romanian)',
            'view' => 'emails.academy.invitations.unconfirmed',
            'subject' => 'Invitație în Academia The Romanian Music Industry Awards',
        ],
        'english' => [
            'label' => 'English',
            'view' => 'emails.academy.invitations.english',
            'subject' => 'Invitation to The Romanian Music Industry Awards Academy',
        ],
    ],

];
