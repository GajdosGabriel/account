<?php

namespace App\Services\Messaging;

/**
 * Z odpovede oddelí to, čo klient naozaj napísal, od citácie pôvodnej
 * správy a podpisu.
 *
 * Nejde o dokonalý parser – je to heuristika pre bežné poštové programy
 * (Gmail, Outlook, Apple Mail, Thunderbird, slovenské a české lokalizácie).
 * Celý text správy sa ukladá vždy, takže chyba tu nič nestratí.
 */
class ReplyExtractor
{
    /** Riadok, od ktorého začína citácia („Dňa … napísal:“). */
    private const QUOTE_HEADERS = [
        '/^On\b.+\bwrote:?\s*$/i',
        '/^Dňa\b.+\bnapísal[a]?:?\s*$/iu',
        '/^D[ňn]a\b.+\bnap[ií]sal[a]?\b.*:?\s*$/iu',
        '/^Dne\b.+\bnapsal[a]?:?\s*$/iu',
        '/^Am\b.+\bschrieb\b.*:?\s*$/iu',
        '/^Le\b.+\ba écrit\s*:?\s*$/iu',
        '/^\S+@\S+\s+(wrote|napísal|napsal|schrieb):?\s*$/iu',
        '/^-{2,}\s*(Original Message|Pôvodná správa|Původní zpráva|Ursprüngliche Nachricht|Forwarded message|Preposlaná správa)\s*-{2,}/iu',
        '/^_{10,}\s*$/',
    ];

    /** Hlavička citovanej správy z Outlooku: „From: …“ a hneď pod ním „Sent: …“. */
    private const OUTLOOK_FROM = '/^\*?(From|Od|Von|De)\s*:\*?\s/iu';

    private const OUTLOOK_NEXT = '/^\*?(Sent|Date|Odoslané|Odesláno|Dátum|Datum|Gesendet|Envoyé|To|Komu|An)\s*:/iu';

    public function extract(?string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", (string) $text);
        $lines = explode("\n", $text);
        $cut = count($lines);

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);

            if ($this->isQuoteHeader($trimmed, $lines[$i + 1] ?? '')) {
                $cut = $i;
                break;
            }

            // Podpis podľa konvencie „-- “
            if ($line === '-- ' || $trimmed === '--') {
                $cut = $i;
                break;
            }
        }

        $kept = array_slice($lines, 0, $cut);

        // Citované riadky „>“ na konci správy (bez úvodnej hlavičky)
        while ($kept !== [] && (trim(end($kept)) === '' || str_starts_with(ltrim(end($kept)), '>'))) {
            array_pop($kept);
        }

        $reply = trim(implode("\n", $kept));

        return $reply === '' ? trim($text) : $reply;
    }

    private function isQuoteHeader(string $line, string $next): bool
    {
        if ($line === '') {
            return false;
        }

        foreach (self::QUOTE_HEADERS as $pattern) {
            if (preg_match($pattern, $line)) {
                return true;
            }
        }

        // „On Tue, 3 Oct 2026 Jan Novák <jan@x.sk>“ zalomené na dva riadky
        if (preg_match('/^(On|Dňa|Dna|Dne|Am)\b/iu', $line)
            && preg_match('/\b(wrote|napísal[a]?|napsal[a]?|schrieb)\b.*:?\s*$/iu', trim($next))
            && mb_strlen($line) < 200) {
            return true;
        }

        return preg_match(self::OUTLOOK_FROM, $line) === 1
            && preg_match(self::OUTLOOK_NEXT, trim($next)) === 1;
    }

    /** Text z HTML, keď mail nemá textovú časť. */
    public static function htmlToText(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $html = preg_replace('#<(script|style|head)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        // Citácia v Gmaili/Apple Mail je v <blockquote> – označíme ju „>“,
        // aby ju extract() vedel odrezať.
        $html = preg_replace_callback('#<blockquote\b[^>]*>(.*?)</blockquote>#is', function ($m) {
            $inner = self::htmlToText($m[1]);

            return "\n".preg_replace('/^/m', '> ', $inner)."\n";
        }, $html) ?? $html;
        $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</(p|div|li|tr|h[1-6])>#i', "\n", $html) ?? $html;

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
