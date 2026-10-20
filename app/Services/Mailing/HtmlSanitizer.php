<?php

namespace App\Services\Mailing;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Telo kampane z HTML editora sa vkladá do e-mailu bez escapovania.
 * Editor v prehliadači sa dá obísť priamym požiadavkom, takže pred
 * vypísaním prejde HTML aj cez túto bielu listinu.
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h2', 'h3', 'ul', 'ol', 'li', 'a'];

    private const ALLOWED_HREF = '/^(https?:\/\/|mailto:|\/)/i';

    public static function sanitize(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $dom = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div id="root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('root');

        if (! $root) {
            return '';
        }

        self::clean($root);

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return trim($result);
    }

    /**
     * Čistý text pre miesta, kde sa HTML vykresliť nedá — predmet e-mailu,
     * meta description, výpis v tabuľke.
     */
    public static function toText(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $withBreaks = preg_replace('/<(br|\/p|\/h2|\/h3|\/li)\s*\/?>/i', ' ', $html);

        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private static function clean(DOMNode $node): void
    {
        // Kópia zoznamu — deti sa počas prechodu presúvajú aj mažú.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            self::clean($child);

            if (! in_array(strtolower($child->tagName), self::ALLOWED_TAGS, true)) {
                self::unwrap($child);

                continue;
            }

            self::stripAttributes($child);
        }
    }

    /** Nepovolenú značku zahodíme, jej text ponecháme. */
    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        foreach (iterator_to_array($element->childNodes) as $child) {
            $parent->insertBefore($child, $element);
        }

        $parent->removeChild($element);
    }

    private static function stripAttributes(DOMElement $element): void
    {
        $isLink = strtolower($element->tagName) === 'a';

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $keep = $isLink
                && strtolower($attribute->name) === 'href'
                && preg_match(self::ALLOWED_HREF, $attribute->value) === 1;

            if (! $keep) {
                $element->removeAttribute($attribute->name);
            }
        }

        if ($isLink) {
            $element->setAttribute('rel', 'noopener nofollow');
            $element->setAttribute('target', '_blank');
        }
    }
}
