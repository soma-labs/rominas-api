<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Support;

use Rominas\Academy\Member\DataTransferObjects\InvitationTemplateData;

/**
 * Reads the academy invitation templates from config/academy-invitations.php, so the rest of the code
 * never touches that config directly.
 */
class InvitationTemplates
{
    /**
     * @return list<InvitationTemplateData>
     */
    public static function all(): array
    {
        return array_map(self::find(...), self::keys());
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        /** @var array<string, mixed> $templates */
        $templates = config('academy-invitations.templates', []);

        return array_keys($templates);
    }

    /**
     * The template with this key; the default one when the key is null or unknown.
     */
    public static function find(?string $key): InvitationTemplateData
    {
        $key = in_array($key, self::keys(), true) ? $key : (string) config('academy-invitations.default');

        /** @var array{label: string, view: string, subject: string} $template */
        $template = config("academy-invitations.templates.{$key}");

        return new InvitationTemplateData(
            key: $key,
            label: $template['label'],
            view: $template['view'],
            subject: $template['subject'],
        );
    }
}
