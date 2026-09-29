<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Validation Language Lines (Romanian)
|--------------------------------------------------------------------------
|
| Only the rules the member-facing routes actually use (see
| SaveCategoryRankingRequest, CreateMemberProposalRequest,
| MemberMagicLink{Request,Verify}Request, and the public voting
| RequestVotingLinkRequest / SubmitBallotRequest). Any rule not listed here falls
| back to lang/en/validation.php via `fallback_locale`.
|
*/

return [

    'array' => 'Câmpul :attribute trebuie să fie un tablou.',

    'distinct' => 'Câmpul :attribute conține o valoare duplicată.',

    'email' => 'Câmpul :attribute trebuie să fie o adresă de e-mail validă.',

    'integer' => 'Câmpul :attribute trebuie să fie un număr întreg.',

    'max' => [
        'array' => 'Câmpul :attribute nu poate avea mai mult de :max elemente.',
        'string' => 'Câmpul :attribute nu poate avea mai mult de :max caractere.',
    ],

    'min' => [
        'array' => 'Câmpul :attribute trebuie să aibă cel puțin :min elemente.',
        'string' => 'Câmpul :attribute trebuie să aibă cel puțin :min caractere.',
    ],

    'present' => 'Câmpul :attribute trebuie să fie prezent.',

    'required' => 'Câmpul :attribute este obligatoriu.',

    'string' => 'Câmpul :attribute trebuie să fie un text.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | Human-readable field names for the :attribute placeholder above.
    | `nominees.*` covers each indexed slot of a ranked-nominees array.
    |
    */

    'attributes' => [
        'email' => 'adresa de e-mail',
        'token' => 'tokenul',
        'name' => 'numele',
        'position' => 'funcția',
        'company' => 'compania',
        'phone' => 'telefonul',
        'reason' => 'motivul',
        'nominees' => 'nominalizații',
        'nominees.*' => 'nominalizatul',
        'categories' => 'categoriile',
        'categories.*.category_id' => 'categoria',
        'categories.*.nominees' => 'nominalizații',
        'categories.*.nominees.*' => 'nominalizatul',
    ],

];
