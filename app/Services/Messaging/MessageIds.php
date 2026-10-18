<?php

namespace App\Services\Messaging;

use App\Models\Mailbox;
use Illuminate\Support\Str;

/**
 * Message-ID sa v hlavičkách píše v lomených zátvorkách a niektoré
 * programy menia veľkosť písmen. V databáze ich držíme bez zátvoriek
 * a malými písmenami, aby sa párovanie nepokazilo na formáte.
 */
class MessageIds
{
    public static function generate(Mailbox $mailbox): string
    {
        return strtolower(Str::uuid().'@'.$mailbox->domain());
    }

    public static function normalize(?string $id): ?string
    {
        $id = strtolower(trim((string) $id, " \t\r\n<>"));

        return $id === '' ? null : $id;
    }

    /**
     * Všetky ID z hlavičky typu References („<a@x> <b@y>“).
     *
     * @return array<int, string>
     */
    public static function parseList(?string $header): array
    {
        if ($header === null || trim($header) === '') {
            return [];
        }

        preg_match_all('/<([^<>\s]+)>/', $header, $matches);

        $ids = $matches[1] !== [] ? $matches[1] : preg_split('/[\s,]+/', $header);

        return array_values(array_unique(array_filter(array_map(self::normalize(...), $ids))));
    }
}
