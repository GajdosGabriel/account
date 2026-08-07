<?php

namespace App\Services\Registry;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Overenie IČ DPH v systéme VIES (EÚ).
 *
 * Používa sa na dve veci:
 *  1. potvrdenie, že zákazník je platiteľ DPH,
 *  2. rozhodnutie o prenesení daňovej povinnosti (reverse charge)
 *     pri fakturácii do iného členského štátu.
 */
class ViesValidator
{
    /**
     * @return array{valid: bool, checked: bool, country?: string, number?: string, name?: string, address?: string, error?: string}
     */
    public function validate(string $vatNumber): array
    {
        [$country, $number] = $this->split($vatNumber);

        if (! $country || ! $number) {
            return ['valid' => false, 'checked' => false, 'error' => __('messages.registry.vat_prefix')];
        }

        if (! config('accounts.vies.enabled')) {
            return ['valid' => false, 'checked' => false, 'error' => __('messages.registry.vies_disabled')];
        }

        $key = "vies:{$country}{$number}";

        // Cachujeme len definitívnu odpoveď VIES. Výpadky ani chybové hlášky
        // nie – tie sú preložené podľa jazyka volajúceho.
        if (($cached = Cache::get($key)) !== null) {
            return $cached;
        }

        try {
            $response = Http::timeout(config('accounts.vies.timeout'))
                ->acceptJson()
                ->get(config('accounts.vies.base_url')."/ms/{$country}/vat/{$number}");

            if ($response->failed()) {
                return ['valid' => false, 'checked' => false, 'error' => __('messages.registry.vies_failed', ['status' => $response->status()])];
            }

            $data = $response->json();

            if (($data['userError'] ?? 'VALID') !== 'VALID') {
                return ['valid' => false, 'checked' => true, 'error' => 'VIES: '.$data['userError']];
            }

            $result = [
                'valid' => (bool) ($data['isValid'] ?? $data['valid'] ?? false),
                'checked' => true,
                'country' => $country,
                'number' => $number,
                'name' => $data['name'] ?? null,
                'address' => $data['address'] ?? null,
            ];

            Cache::put($key, $result, now()->addDay());

            return $result;
        } catch (\Throwable $e) {
            Log::warning('VIES validation failed', ['vat' => $country.$number, 'error' => $e->getMessage()]);

            return ['valid' => false, 'checked' => false, 'error' => __('messages.registry.vies_unavailable')];
        }
    }

    /**
     * Má sa na faktúru uplatniť prenesenie daňovej povinnosti?
     * Platí pre platné IČ DPH z iného členského štátu EÚ.
     */
    public function appliesReverseCharge(?string $vatNumber, string $customerCountry): bool
    {
        $home = config('accounts.billing.home_country');

        if (! $vatNumber || strtoupper($customerCountry) === strtoupper($home)) {
            return false;
        }

        if (! in_array(strtoupper($customerCountry), self::EU_COUNTRIES, true)) {
            return false;
        }

        return $this->validate($vatNumber)['valid'] ?? false;
    }

    /** @return array{0: ?string, 1: ?string} */
    protected function split(string $vatNumber): array
    {
        $clean = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $vatNumber) ?? '');

        if (strlen($clean) < 3) {
            return [null, null];
        }

        $country = substr($clean, 0, 2);
        $number = substr($clean, 2);

        if (! in_array($country, self::EU_COUNTRIES, true)) {
            return [null, null];
        }

        return [$country, $number];
    }

    public const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES', 'FI', 'FR',
        'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO',
        'SE', 'SI', 'SK', 'XI',
    ];
}
