<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Adresa webhooku musí viesť von na internet cez https.
 *
 * Account posiela na ňu POST zo svojho servera. Bez tejto kontroly by sa
 * dal webhook nasmerovať na interné služby (127.0.0.1, 10.x, metadáta
 * cloudu 169.254.169.254…) a odpoveď by sa ukázala v histórii doručení.
 * Lokálne vývojové prostredie to môže povoliť cez
 * `accounts.webhooks.allow_private`.
 */
class PublicWebhookUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::allowed($value)) {
            $fail('Adresa webhooku musí byť verejná https adresa (nie localhost ani vnútorná sieť).');
        }
    }

    /** Použije sa aj tesne pred odoslaním – DNS sa medzitým mohlo zmeniť. */
    public static function allowed(string $url): bool
    {
        if (config('accounts.webhooks.allow_private')) {
            return true;
        }

        $parts = parse_url($url);
        $host = $parts['host'] ?? null;

        if (($parts['scheme'] ?? null) !== 'https' || ! $host || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = trim($host, '[]');

        $ips = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : array_merge(
                array_column(@dns_get_record($host, DNS_A) ?: [], 'ip'),
                array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'),
            );

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}
