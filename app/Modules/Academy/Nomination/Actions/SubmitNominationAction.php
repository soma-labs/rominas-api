<?php

declare(strict_types=1);

namespace Rominas\Academy\Nomination\Actions;

use Illuminate\Validation\ValidationException;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\Nomination\Enums\NominationStatus;
use Rominas\Academy\Nomination\Model\Nomination;

/**
 * Finalizes a member's ballot. Guards: nominations must be open and the ballot still a draft. The ballot
 * must hold at least one nominee in at least one category — the other categories may stay empty and a
 * category may hold fewer than 5 — else a 422. On success the ballot is locked (`submitted`).
 */
class SubmitNominationAction
{
    public function __construct(
        private readonly ResolveOpenNominationEditionAction $resolveOpenEdition,
    ) {}

    public function execute(Member $member): Nomination
    {
        $edition = $this->resolveOpenEdition->execute();

        $nomination = Nomination::query()->forMember($member)->forEdition($edition)->first();

        // No ballot yet, or a draft left without picks (saving an empty list clears a category).
        if ($nomination === null || ! $nomination->rankings()->exists()) {
            throw ValidationException::withMessages([
                'nominations' => __('Add at least one nominee before submitting.'),
            ]);
        }

        if ($nomination->status === NominationStatus::Submitted) {
            throw ValidationException::withMessages([
                'nominations' => __('Your nominations have already been submitted.'),
            ]);
        }

        $nomination->status = NominationStatus::Submitted;
        $nomination->submitted_at = now();
        $nomination->save();

        return $nomination;
    }
}
