<?php

declare(strict_types=1);

namespace Rominas\Categories\Actions;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Cleans a category's eligibility criteria, which the admin writes in a rich-text editor and the academy
 * dashboard renders as HTML. Only what the editor produces survives: paragraphs, H2/H3 headings, lists and
 * links. Any other element is unwrapped to its text, except those whose content is not text (scripts,
 * styles, embeds), which are dropped whole. Returns null when nothing but empty markup is left, as an
 * emptied editor sends `<p></p>`.
 */
class SanitizeEligibilityCriteriaAction
{
    private const array DROPPED_ELEMENTS = [
        'script', 'style', 'template', 'noscript', 'iframe', 'object', 'embed', 'svg', 'math', 'textarea', 'select',
    ];

    public function execute(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $config = (new HtmlSanitizerConfig())
            ->defaultAction(HtmlSanitizerAction::Block)
            ->allowElement('p')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('br')
            ->allowElement('a', ['href'])
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->forceAttribute('a', 'target', '_blank')
            ->forceAttribute('a', 'rel', 'noopener noreferrer');

        foreach (self::DROPPED_ELEMENTS as $element) {
            $config = $config->dropElement($element);
        }

        $sanitized = trim((new HtmlSanitizer($config))->sanitize($html));

        return trim(strip_tags($sanitized)) === '' ? null : $sanitized;
    }
}
