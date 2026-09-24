<?php

declare(strict_types=1);

namespace Rominas\Auth\MagicLink\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Rominas\Auth\MagicLink\Model\MagicLinkToken;

/**
 * Confirms a passwordless magic link for a participant guard. The token is single-use — consumed on
 * a valid match — and a 48-char random token needs no attempt counter. On success the account is
 * resolved through the guard's own auth provider and returned; the caller mints the Sanctum token.
 * Returns null when the token is missing, expired, or wrong.
 */
class VerifyMagicLinkAction
{
    public function execute(string $guard, string $email, string $token): ?Authenticatable
    {
        $record = MagicLinkToken::query()
            ->where('email', $email)
            ->where('guard', $guard)
            ->first();

        if ($record === null || $record->isExpired()) {
            return null;
        }

        if (! Hash::check($token, $record->token)) {
            return null;
        }

        // Single-use: consume the token before returning the account.
        $record->delete();

        $provider = config("auth.guards.{$guard}.provider");

        if (! is_string($provider)) {
            return null;
        }

        return Auth::createUserProvider($provider)?->retrieveByCredentials(['email' => $email]);
    }
}
