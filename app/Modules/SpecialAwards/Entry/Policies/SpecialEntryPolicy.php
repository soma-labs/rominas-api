<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Entry\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;
use Rominas\Users\Model\User;

/**
 * Admin-side review of special-category entries and entry of an appointed result. Gates on the
 * `specialAwards` permission. The member-facing side (propose, vote, withdraw) is guarded by
 * `auth:member` with implicit ownership, not this policy.
 */
class SpecialEntryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('specialAwards');
    }

    public function view(User $user, SpecialEntry $model): bool
    {
        return $user->can('specialAwards');
    }

    public function create(User $user): bool
    {
        return $user->can('specialAwards');
    }

    public function update(User $user, SpecialEntry $model): bool
    {
        return $user->can('specialAwards');
    }
}
