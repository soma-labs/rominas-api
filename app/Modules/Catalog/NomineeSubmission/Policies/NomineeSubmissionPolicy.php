<?php

declare(strict_types=1);

namespace Rominas\Catalog\NomineeSubmission\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;
use Rominas\Users\Model\User;

/**
 * Admin reconciliation of free-text nominee submissions. Gates on the `nomineeSubmissions` permission
 * (granted to `admin`); every action — listing, viewing, and the link/create/reject decisions — is an
 * `update` on the reconciliation queue.
 */
class NomineeSubmissionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('nomineeSubmissions');
    }

    public function view(User $user, NomineeSubmission $model): bool
    {
        return $user->can('nomineeSubmissions');
    }

    public function update(User $user, NomineeSubmission $model): bool
    {
        return $user->can('nomineeSubmissions');
    }
}
