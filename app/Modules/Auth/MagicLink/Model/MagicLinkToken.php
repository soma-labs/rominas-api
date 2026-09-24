<?php

declare(strict_types=1);

namespace Rominas\Auth\MagicLink\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A pending passwordless magic link, keyed by (email, guard). One row per address per participant
 * guard; the (hashed) token is single-use — consumed on verification. Guard-agnostic: the same table
 * serves any Sanctum participant guard (`member`, later `critic`). See
 * Rominas\Auth\MagicLink\Actions\SendMagicLinkAction and VerifyMagicLinkAction.
 *
 * @mixin IdeHelperMagicLinkToken
 */
#[Fillable([
    'email',
    'guard',
    'token',
    'created_at',
    'expires_at',
])]
class MagicLinkToken extends Model
{
    protected $table = 'magic_link_tokens';

    // Composite primary key (email, guard); lookups use explicit where clauses, not whereKey().
    protected $primaryKey = 'email';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Only `created_at` exists on this table (no `updated_at`); manage it explicitly.
     */
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Each link carries its own expiry, set when it is issued (invitations live longer than sign-in
     * links — see config/magic-link.php), so verification needs no knowledge of the e-mail type.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
