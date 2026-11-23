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
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h2', 'h3', 'ul', 'ol', 'li', 'a', 'span'];

    /** Blokové značky, ktoré sa zahodia, ale ich obsah musí ostať na vlastnom riadku. */
    private const LINE_TAGS = ['div', 'blockquote', 'pre', 'h1', 'h4', 'h5', 'h6'];

    private const ALLOWED_HREF = '/^(https?:\/\/|mailto:|\/)/i';

    /** Štýly z editora (farba, pozadie, zarovnanie, veľkosť) – nič, čo by načítalo cudzí obsah. */
    private const ALLOWED_STYLES = ['color', 'background-color', 'text-align', 'font-size', 'font-weight', 'font-style', 'text-decoration'];

    private const ALLOWED_STYLE_VALUE = '/^(#[0-9a-f]{3,8}|rgba?\(\s*[\d.]+%?\s*,\s*[\d.]+%?\s*,\s*[\d.]+%?\s*(,\s*[\d.]+\s*)?\)|[a-z-]+|\d+(\.\d+)?(px|em|rem|%))$/i';

    /** Ponechá len povolené vlastnosti s bezpečnými hodnotami; prázdny výsledok = žiadny style. */
    public static function cleanStyle(string $style): string
    {
        $kept = [];

        foreach (explode(';', $style) as $declaration) {
            [$property, $value] = array_pad(array_map('trim', explode(':', $declaration, 2)), 2, '');
            $property = strtolower($property);

            if (in_array($property, self::ALLOWED_STYLES, true) && preg_match(self::ALLOWED_STYLE_VALUE, $value) === 1) {
                $kept[] = "{$property}: {$value}";
            }
        }

        return implode('; ', $kept);
    }

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
                // Editor (contenteditable) píše riadky ako <div>; bez zalomenia by sa zlepili („S pozdravomGabriel“).
                if (in_array(strtolower($child->tagName), self::LINE_TAGS, true) && self::endsInline($child->previousSibling)) {
                    $child->parentNode->insertBefore($child->ownerDocument->createElement('br'), $child);
                }

                self::unwrap($child);

                continue;
            }

            self::stripAttributes($child);
        }
    }

    /** Predchádza riadku text alebo inline obsah? Po <br>, odseku či nadpise je nový riadok už daný. */
    private static function endsInline(?DOMNode $node): bool
    {
        while ($node && $node->nodeType === XML_TEXT_NODE && trim($node->textContent) === '') {
            $node = $node->previousSibling;
        }

        return $node !== null
            && ! ($node instanceof DOMElement && in_array(strtolower($node->tagName), ['br', 'p', 'h2', 'h3', 'ul', 'ol'], true));
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
            if (strtolower($attribute->name) === 'style' && ($style = self::cleanStyle($attribute->value)) !== '') {
                $element->setAttribute('style', $style);

                continue;
            }

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
