<?php

namespace App\Services\Registry;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Vyhľadanie firmy podľa IČO vo verejných registroch.
 *  - SK: RPO (Register právnických osôb, ŠÚ SR) – api.statistics.sk
 *  - CZ: ARES (Ministerstvo financií ČR) – ares.gov.cz
 *  - DIČ dopĺňame z Registra účtovných závierok (registeruz.sk), RPO ho nevracia.
 *
 * API sú verejné a bez kľúča, ale nie sú zmluvne garantované –
 * preto je každé volanie obalené try/catch a výsledok je len
 * "najlepší pokus". Formulár musí fungovať aj keď register nebeží.
 */
class IcoLookupService
{
    /**
     * @return array{found: bool, source: string, name?: string, street?: string, city?: string, postal_code?: string, country?: string, legal_form?: string, raw?: array<string, mixed>, error?: string}
     */
    public function lookup(string $ico, string $country = 'sk'): array
    {
        $ico = $this->normalize($ico);
        $country = strtolower($country) === 'cz' ? 'cz' : 'sk';
        $source = $country === 'cz' ? 'ares' : 'rpo';

        if (! $this->isValidChecksum($ico)) {
            return ['found' => false, 'source' => $source, 'error' => __('validation.ico_checksum')];
        }

        if (! config('accounts.rpo.enabled')) {
            return ['found' => false, 'source' => $source, 'error' => __('messages.registry.ico_disabled')];
        }

        // Cachujeme iba úspešné nájdenie. Chybové hlášky sú preložené podľa
        // jazyka volajúceho, takže by sa prvý jazyk zamrazil pre všetkých.
        if (($cached = Cache::get("{$source}:{$ico}")) !== null) {
            return $cached;
        }

        try {
            $mapped = $country === 'cz' ? $this->fromAres($ico) : $this->fromRpo($ico);

            if (! $mapped) {
                return ['found' => false, 'source' => $source, 'error' => __('messages.registry.ico_not_found')];
            }

            Cache::put("{$source}:{$ico}", $mapped, now()->addDay());

            return $mapped;
        } catch (\Throwable $e) {
            Log::warning('Registry lookup failed', ['ico' => $ico, 'source' => $source, 'error' => $e->getMessage()]);

            return ['found' => false, 'source' => $source, 'error' => __('messages.registry.rpo_unavailable')];
        }
    }

    /**
     * HTTP klient s explicitným CA bundle (obchádza zastaraný CA bundle v prostredí).
     * Overovanie SSL ostáva zapnuté – buď proti pribalenému cacert.pem, alebo default.
     */
    protected function client(): PendingRequest
    {
        $client = Http::timeout((int) config('accounts.rpo.timeout', 10))->acceptJson();

        $caBundle = base_path('resources/certs/cacert.pem');

        if (is_file($caBundle)) {
            $client->withOptions(['verify' => $caBundle]);
        }

        return $client;
    }

    /**
     * Slovenský register RPO (vnorené JSON, historizované hodnoty).
     *
     * @return array<string, mixed>|null
     */
    protected function fromRpo(string $ico): ?array
    {
        $response = $this->client()
            ->get(config('accounts.rpo.base_url').'/search', ['identifier' => $ico]);

        $response->throw();

        $entity = $response->json('results.0');

        if (! $entity) {
            return null;
        }

        $address = $this->currentEntry($entity['addresses'] ?? []);
        $legalFormText = $this->currentValue($entity['legalForms'] ?? []);
        $name = $this->currentValue($entity['fullNames'] ?? []);

        // zapis v registri: "Sa/1142/B" = oddiel / vlozka
        $court = $this->currentValue($entity['sourceRegister']['registrationOffices'] ?? []);
        [$section, $insert] = $this->splitRegistrationNumber(
            $this->currentValue($entity['sourceRegister']['registrationNumbers'] ?? [])
        );

        return [
            'found' => true,
            'source' => 'rpo',
            'name' => $name,
            'legal_name' => $name,
            'legal_form' => $this->guessLegalForm($legalFormText, $name),
            'legal_form_text' => $legalFormText,

            'ico' => $ico,
            // RPO DIČ neposkytuje – dopĺňa sa z Registra účtovných závierok.
            'dic' => $this->dicFromRuz($ico),
            // IČ DPH nie je vo verejnom registri (len v zozname platiteľov DPH
            // Finančnej správy) – nedá sa spoľahlivo odvodiť, dopĺňa ho používateľ.
            'ic_dph' => null,

            // ulicu a cislo drzime zvlast - fakturacne systemy to tak chcu
            'street' => data_get($address, 'street') ?: null,
            'street_no' => data_get($address, 'buildingNumber') ?: data_get($address, 'regNumber'),
            'city' => data_get($address, 'municipality.value') ?: null,
            'postal_code' => data_get($address, 'postalCodes.0') ?: null,
            'region' => data_get($address, 'region.value') ?: null,
            'country' => data_get($address, 'country.code') === '203' ? 'CZ' : 'SK',

            // zapis v registri - povinny udaj na fakture
            'register_court' => $court,
            'register_section' => $section,
            'register_insert' => $insert,
            'established_at' => is_array($entity['establishment'] ?? null)
                ? $this->currentValue($entity['establishment']) ?? data_get($entity, 'establishment.0.validFrom')
                : ($entity['establishment'] ?? null),

            'raw' => $entity,
        ];
    }

    /**
     * Český register ARES (ploché JSON).
     *
     * @return array<string, mixed>|null
     */
    protected function fromAres(string $ico): ?array
    {
        $response = $this->client()
            ->get("https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/{$ico}");

        if ($response->status() === 404) {
            return null;
        }

        $response->throw();

        $entity = $response->json();
        $address = $entity['sidlo'] ?? [];

        $streetNo = trim((string) ($address['cisloDomovni'] ?? '')
            .(isset($address['cisloOrientacni']) ? '/'.$address['cisloOrientacni'] : ''));

        $street = $address['nazevUlice'] ?? $address['nazevCastiObce'] ?? null;
        $name = $entity['obchodniJmeno'] ?? null;

        return [
            'found' => true,
            'source' => 'ares',
            'name' => $name,
            'legal_name' => $name,
            'legal_form' => $this->guessLegalForm(null, $name),
            'legal_form_text' => null,

            'ico' => $entity['ico'] ?? $ico,
            // v ČR je DIČ zároveň IČ DPH
            'dic' => $entity['dic'] ?? null,
            'ic_dph' => $entity['dic'] ?? null,

            'street' => $street,
            'street_no' => $streetNo ?: null,
            'city' => $address['nazevObce'] ?? null,
            'postal_code' => isset($address['psc']) ? (string) $address['psc'] : null,
            'region' => $address['nazevKraje'] ?? null,
            'country' => $address['kodStatu'] ?? 'CZ',

            'register_court' => null,
            'register_section' => null,
            'register_insert' => null,
            'established_at' => $entity['datumVzniku'] ?? null,

            'raw' => $entity,
        ];
    }

    /**
     * DIČ z Registra účtovných závierok (registeruz.sk), ktoré RPO neposkytuje.
     * Doplnkový zdroj – jeho výpadok nesmie zhodiť vyhľadanie z RPO, preto sa
     * chyby prehltnú a vráti sa prázdna hodnota.
     */
    protected function dicFromRuz(string $ico): ?string
    {
        try {
            $ids = $this->client()
                ->get('https://www.registeruz.sk/cruz-public/api/uctovne-jednotky', [
                    'ico' => $ico,
                    'zmenene-od' => '2000-01-01',
                ])
                ->json('id') ?? [];

            // K jednému IČO môže existovať viac záznamov vrátane zmazaných;
            // berie sa prvý živý s vyplneným DIČ.
            foreach (array_slice($ids, 0, 5) as $id) {
                $entity = $this->client()
                    ->get('https://www.registeruz.sk/cruz-public/api/uctovna-jednotka', ['id' => $id])
                    ->json();

                if (($entity['stav'] ?? null) === 'ZMAZANÉ') {
                    continue;
                }

                if ($dic = $entity['dic'] ?? null) {
                    return (string) $dic;
                }
            }
        } catch (\Throwable) {
            // Doplnkový register je nedostupný – vrátime aspoň údaje z RPO.
        }

        return null;
    }

    /**
     * Registrové číslo z RPO chodí ako "Sa/1142/B" – prvý diel je oddiel,
     * zvyšok vložka. Keď formát nesedí, dáme všetko do vložky.
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected function splitRegistrationNumber(?string $number): array
    {
        if (blank($number)) {
            return [null, null];
        }

        $parts = explode('/', $number, 2);

        return count($parts) === 2 ? [$parts[0], $parts[1]] : [null, $number];
    }

    /**
     * Z historizovaného poľa vyberie aktuálnu (bez `validTo`) položku a vráti jej `value`.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function currentValue(array $items): ?string
    {
        return $this->currentEntry($items)['value'] ?? null;
    }

    /**
     * Z historizovaného poľa vyberie aktuálnu (bez `validTo`) položku, inak poslednú.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    protected function currentEntry(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        $active = array_values(array_filter($items, fn ($i) => is_array($i) && empty($i['validTo'])));
        $pick = $active ? end($active) : end($items);

        return is_array($pick) ? $pick : [];
    }

    /**
     * Register vracia právnu formu ako text – prevedieme ju na náš číselník.
     * Keď sa netrafíme, formulár ju nechá prázdnu a doplní ju človek.
     */
    protected function guessLegalForm(?string $text, ?string $name): ?string
    {
        $haystack = mb_strtolower(($text ?? '').' '.($name ?? ''));

        return match (true) {
            str_contains($haystack, 's. r. o') || str_contains($haystack, 's.r.o') || str_contains($haystack, 'ručením obmedzen') => 'sro',
            str_contains($haystack, 'a. s') || str_contains($haystack, 'a.s') || str_contains($haystack, 'akciová') => 'as',
            str_contains($haystack, 'k. s') || str_contains($haystack, 'komanditná') => 'ks',
            str_contains($haystack, 'v. o. s') || str_contains($haystack, 'verejná obchodná') => 'vos',
            str_contains($haystack, 'družstvo') => 'druzstvo',
            str_contains($haystack, 'živnost') => 'zivnost',
            str_contains($haystack, 'nezisk') || str_contains($haystack, 'občianske združenie') => 'nezisk',
            default => null,
        };
    }

    public function normalize(string $ico): string
    {
        return str_pad(preg_replace('/\D/', '', $ico) ?? '', 8, '0', STR_PAD_LEFT);
    }

    /**
     * Kontrolná číslica slovenského/českého IČO (mod 11).
     */
    public function isValidChecksum(string $ico): bool
    {
        $ico = $this->normalize($ico);

        if (strlen($ico) !== 8) {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < 7; $i++) {
            $sum += (int) $ico[$i] * (8 - $i);
        }

        $remainder = $sum % 11;

        $check = match ($remainder) {
            0 => 1,
            1 => 0,
            default => 11 - $remainder,
        };

        return $check === (int) $ico[7];
    }
}
