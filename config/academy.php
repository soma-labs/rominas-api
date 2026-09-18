<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Member proposals — per-member cap
    |--------------------------------------------------------------------------
    |
    | The maximum number of future-member proposals a single academy member may
    | ever create. This is a lifetime cap: every proposal the member has made
    | counts toward it, regardless of status (pending / approved / rejected).
    | Enforced in CreateMemberProposalAction.
    |
    */

    'max_proposals_per_member' => 5,

];
