<?php

declare(strict_types=1);

namespace Rominas\Catalog\NomineeSubmission\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Rominas\Catalog\NomineeSubmission\Enums\NomineeSubmissionStatus;
use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;
use Rominas\Users\Model\User;

/**
 * Reconciles a pending submission by materializing a brand-new canonical Catalog entity of its type
 * (mirrors how approving a MemberProposal creates a real Member), then links the submission to it via
 * {@see LinkNomineeSubmissionAction}. The name defaults to what the member typed; an admin may correct it.
 *
 * Refuses when an entity with the same slug already exists — that means the admin should link to it
 * instead of creating a duplicate.
 */
class CreateNomineeFromSubmissionAction
{
    public function __construct(
        private readonly LinkNomineeSubmissionAction $link,
    ) {}

    public function execute(NomineeSubmission $submission, User $reviewer, ?string $name = null, ?string $note = null): NomineeSubmission
    {
        if ($submission->status !== NomineeSubmissionStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only a pending submission can be reconciled.',
            ]);
        }

        $name = trim($name ?? $submission->raw_name);
        $modelClass = $submission->nominee_type->modelClass();

        if ($modelClass::query()->where('slug', '=', Str::slug($name))->exists()) {
            throw ValidationException::withMessages([
                'name' => 'An entry with this name already exists — link to it instead of creating a new one.',
            ]);
        }

        return DB::transaction(function () use ($submission, $modelClass, $name, $reviewer, $note): NomineeSubmission {
            /** @var Model $nominee */
            $nominee = $modelClass::query()->create(['name' => $name]);

            return $this->link->execute($submission, (int) $nominee->getKey(), $reviewer, $note);
        });
    }
}
