<?php

namespace App\Services\Mailing;

/**
 * Z chyby SMTP odvodí, či ide o trvalo nedoručiteľnú adresu (hard), dočasný problém
 * adresáta (soft: plná schránka, dočasná chyba) alebo o niečo, čo adresu nevinní
 * (politika odosielateľa, spam, sieť) a kontaktu sa nemá pripočítať.
 */
class BounceClassifier
{
    public const HARD = 'hard';

    public const SOFT = 'soft';

    public const NONE = 'none';

    /** @return array{type: string, code: ?string, reason: string} */
    public function classify(string $message): array
    {
        $code = null;
        $enhanced = null;
        if (preg_match('/\b([245]\d{2})[ -]([245]\.\d{1,3}\.\d{1,3})\b/', $message, $m)) {
            [$code, $enhanced] = [$m[1], $m[2]];
        } elseif (preg_match('/got code "?([245]\d{2})"?/i', $message, $m) || preg_match('/\b([245]\d{2})\b/', $message, $m)) {
            $code = $m[1];
        }

        return ['type' => $this->type($code, $enhanced, $message), 'code' => $enhanced ?? $code, 'reason' => $this->reason($message, $enhanced ?? $code)];
    }

    private function type(?string $code, ?string $enhanced, string $message): string
    {
        if ($code === null) {
            return self::NONE;
        }
        // Politika / reputácia odosielateľa: problém nie je v adrese.
        if ($enhanced && str_starts_with($enhanced, '5.7.') || preg_match('/spam|blocked|blacklist|denied by policy|reputation|relay/i', $message)) {
            return self::NONE;
        }
        if ($enhanced) {
            return match (true) {
                str_starts_with($enhanced, '5.1.') => self::HARD,       // zlá adresa / neexistuje
                str_starts_with($enhanced, '5.2.') => self::SOFT,       // schránka plná / vypnutá
                str_starts_with($enhanced, '4.') => self::SOFT,
                $enhanced === '5.4.4' => self::HARD,                    // doména neexistuje
                default => $this->byCode($code),
            };
        }

        return $this->byCode($code);
    }

    private function byCode(string $code): string
    {
        return match (true) {
            str_starts_with($code, '4') => self::SOFT,
            $code === '552' => self::SOFT,                              // prekročená kvóta
            in_array($code, ['550', '551', '553'], true) => self::HARD,
            default => self::NONE,
        };
    }

    private function reason(string $message, ?string $code): string
    {
        // Odpoveď servera môže obsahovať interné adresy; ukladáme len skrátený text.
        $text = trim(preg_replace('/\s+/', ' ', $message));
        if (preg_match('/with message "?(.+?)"?\.?$/s', $text, $m)) {
            $text = trim(preg_replace('/\s+/', ' ', $m[1]));
        }

        return mb_substr(($code ? $code.': ' : '').$text, 0, 200);
    }
}
