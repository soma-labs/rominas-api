<?php

declare(strict_types=1);

namespace Rominas\Catalog\NomineeSubmission\Actions;

use Rominas\Catalog\Enums\NomineeType;
use Rominas\Catalog\NomineeSubmission\Enums\NomineeSubmissionStatus;
use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;
use Rominas\Catalog\NomineeSubmission\Support\NomineeNameNormalizer;
use Rominas\Editions\Model\Edition;

/**
 * Maps a free-text name typed on the ballot to its staged submission — reusing the existing row for that
 * `(edition, type, normalized name)` if one exists (so identical names across members collapse to one
 * reconciliation item), or creating a fresh pending one. An already-resolved/rejected submission is
 * returned untouched, which is how a name reconciled earlier is "remembered" for later ballots.
 */
class ResolveOrCreateNomineeSubmissionAction
{
    public function execute(Edition $edition, NomineeType $type, string $rawName): NomineeSubmission
    {
        $rawName = trim($rawName);

        return NomineeSubmission::query()->firstOrCreate(
            [
                'edition_id' => $edition->id,
                'nominee_type' => $type->value,
                'normalized_name' => NomineeNameNormalizer::normalize($rawName),
            ],
            [
                'raw_name' => $rawName,
                'status' => NomineeSubmissionStatus::Pending,
            ],
        );
    }
}
