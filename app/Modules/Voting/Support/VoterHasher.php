<?php

declare(strict_types=1);

namespace Rominas\Voting\Support;

/**
 * Pseudonymizes voter personal data (email, IP) into non-reversible HMAC-SHA256 hashes, keyed by the
 * stable `voting.pepper` secret. The Voting module stores only these hashes — never the plaintext (GDPR).
 * The email hash is the stable identity used to enforce one ballot per person; the IP hash is captured at
 * submit for FraudMonitoring. Emails are normalized first ({@see self::normalizeEmail()}) so the spellings
 * of one mailbox never split one person into two identities.
 */
final class VoterHasher
{
    public static function emailHash(string $email): string
    {
        return self::hash(self::normalizeEmail($email));
    }

    /**
     * The one spelling an address is hashed under: trimmed and lowercased, with a `+tag` dropped from the
     * local part on any domain (`ana+1@x.ro` → `ana@x.ro`). Gmail also ignores dots in the local part
     * and answers on googlemail.com, so both are folded (`a.na@googlemail.com` → `ana@gmail.com`). Used
     * for the identity hash only — mail is still sent to the address as the voter typed it.
     */
    public static function normalizeEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));

        $at = strrpos($email, '@');

        if ($at === false) {
            return $email;
        }

        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);

        $plus = strpos($local, '+');

        if ($plus !== false && $plus > 0) {
            $local = substr($local, 0, $plus);
        }

        if ($domain === 'gmail.com' || $domain === 'googlemail.com') {
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return $local . '@' . $domain;
    }

    public static function ipHash(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return self::hash($ip);
    }

    private static function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('voting.pepper'));
    }
}
