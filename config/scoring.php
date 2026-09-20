<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Class weights
    |--------------------------------------------------------------------------
    |
    | The academy-vs-public result weighting is NOT global config — it lives on
    | each edition (`editions.academy_vote_weight` / `public_vote_weight`,
    | defaulting to the client-confirmed 60/40) so an edition can be reweighted
    | independently. Scoring reads it per edition; see the Edition model.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Scoring algorithm
    |--------------------------------------------------------------------------
    |
    | Selects how a category's nominees are normalized and ranked:
    |
    |   'attributed' — the client's formula (PHAZE 3–7): each class's ranking is
    |                  mapped to a fixed rank→score ladder (weights baked in) and
    |                  the two attributed scores are added. The per-edition class
    |                  weights are ignored. This is the default.
    |   'share'      — our per-category share × per-edition weight blend.
    |
    */

    'algorithm' => env('SCORING_ALGORITHM', 'attributed'),

    /*
    |--------------------------------------------------------------------------
    | Attributed-score ladders
    |--------------------------------------------------------------------------
    |
    | Used by the 'attributed' algorithm. Index 0 is rank 1. `academy` is keyed
    | by shortlist position (PHAZE 3); `public` by the public-points ranking
    | (PHAZE 6). Their relative magnitudes bake in the class weighting.
    |
    */

    'attributed' => [
        'academy' => [200, 150, 100, 75, 50],
        'public' => [150, 100, 75, 50, 25],
    ],

    /*
    |--------------------------------------------------------------------------
    | Display precision
    |--------------------------------------------------------------------------
    |
    | Decimal places kept when rounding the normalized shares and final score
    | for storage/presentation. Under the 'share' algorithm ranking never uses
    | these rounded values — it is decided on an exact integer key — so this is
    | purely cosmetic.
    |
    */

    'precision' => 6,

];
