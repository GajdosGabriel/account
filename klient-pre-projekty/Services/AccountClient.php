<?php

namespace App\Services;

use App\Support\Entitlements;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Klient pre Account.
 *
 * Zásada: čítanie degraduje elegantne, zápis nie.
 *
 *  - keď Account nebeží, čítanie vráti poslednú známu hodnotu (fail-open),
 *  - zápis v takom prípade poctivo zlyhá, lebo tichá fronta by vytvorila
 *    konflikty v dátach.
 */
class AccountClient
{
    /* ---------------------------------------------------------------
     | Čítanie
     |---------------------------------------------------------------*/

    /**
     * Oprávnenia firmy v tomto projekte.
     *
     * Volá sa pri každom requeste, preto ide primárne z cache.
     */
    public function entitlements(string $organizationUuid, bool $fresh = false): Entitlements
    {
        $key = "account:ent:{$organizationUuid}";
        $staleKey = "account:ent:stale:{$organizationUuid}";

        if (! $fresh && ($cached = Cache::get($key)) !== null) {
            return new Entitlements($cached);
        }

        try {
            $data = $this->request()->get("/api/v1/organizations/{$organizationUuid}/entitlements")
                ->throw()
                ->json('data');

            Cache::put($key, $data, config('account.cache.ttl'));
            Cache::put($staleKey, $data, config('account.cache.stale_ttl'));

            return new Entitlements($data);
        } catch (\Throwable $e) {
            Log::warning('Account nedostupny, pouzivam poslednu znamu hodnotu', [
                'organization' => $organizationUuid,
                'error' => $e->getMessage(),
            ]);

            // Posledná známa hodnota v rámci povolenej lehoty
            if (($stale = Cache::get($staleKey)) !== null) {
                return new Entitlements($stale, stale: true);
            }

            // Nikdy sme nič nedostali – radšej pustíme dnu, než by sme
            // zamkli platiaceho zákazníka kvôli výpadku siete.
            return Entitlements::failOpen();
        }
    }

    /**
     * Firemné údaje. Cache invaliduje webhook `organization.updated`.
     *
     * @return array<string, mixed>|null
     */
    public function organization(string $organizationUuid): ?array
    {
        $key = "account:org:{$organizationUuid}";

        if (($cached = Cache::get($key)) !== null) {
            return $cached;
        }

        try {
            $data = $this->request()->get("/api/v1/organizations/{$organizationUuid}")
                ->throw()
                ->json('data');

            Cache::put($key, $data, config('account.cache.organization_ttl'));

            return $data;
        } catch (\Throwable $e) {
            Log::warning('Account: nepodarilo sa nacitat organizaciu', ['error' => $e->getMessage()]);

            return Cache::get("account:org:stale:{$organizationUuid}");
        }
    }

    /**
     * Vyhľadanie firmy v registri RPO podľa IČO – na predvyplnenie formulára.
     *
     * @return array<string, mixed>
     */
    public function lookupIco(string $ico): array
    {
        try {
            return $this->request()->post('/api/v1/organizations/lookup', ['ico' => $ico])
                ->throw()
                ->json('data');
        } catch (\Throwable) {
            return ['found' => false, 'error' => 'Register je momentálne nedostupný.'];
        }
    }

    /* ---------------------------------------------------------------
     | Zápis
     |---------------------------------------------------------------*/

    /**
     * Založenie firmy pri registrácii zákazníka.
     *
     * Account najprv hľadá podľa IČO – ak firma už existuje z iného
     * projektu, iba sa na ňu naviaže. Preto NIKDY nevolaj vlastné
     * "create", inak si spravíš tri záznamy tej istej firmy.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createOrLinkOrganization(array $data): array
    {
        $response = $this->request()->post('/api/v1/organizations', $data);

        $this->throwValidationErrors($response);

        return $response->throw()->json('data');
    }

    /**
     * Úprava firmy z formulára projektu.
     *
     * Validačné chyby prehodíme ako bežné Laravel chyby, takže sa
     * v šablóne zobrazia pri poliach a používateľ netuší, že prišli
     * z inej aplikácie.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateOrganization(string $organizationUuid, array $data): array
    {
        $response = $this->request()->put("/api/v1/organizations/{$organizationUuid}", $data);

        $this->throwValidationErrors($response);

        $organization = $response->throw()->json('data');

        Cache::forget("account:org:{$organizationUuid}");

        return $organization;
    }

    /**
     * Nahlásenie spotreby. Púšťaj z plánovača raz denne, prípadne
     * po väčších zmenách. Zlyhanie sa ignoruje – je to len telemetria.
     *
     * @param  array<string, int>  $metrics
     */
    public function reportUsage(string $organizationUuid, array $metrics): ?Entitlements
    {
        try {
            $data = $this->request()
                ->post("/api/v1/organizations/{$organizationUuid}/usage", ['metrics' => $metrics])
                ->throw()
                ->json('data');

            Cache::put("account:ent:{$organizationUuid}", $data, config('account.cache.ttl'));

            return new Entitlements($data);
        } catch (\Throwable $e) {
            Log::warning('Account: hlasenie spotreby zlyhalo', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /* ---------------------------------------------------------------
     | Pomocné
     |---------------------------------------------------------------*/

    public function forget(string $organizationUuid): void
    {
        Cache::forget("account:ent:{$organizationUuid}");
        Cache::forget("account:org:{$organizationUuid}");
    }

    protected function request(): PendingRequest
    {
        return Http::withToken(config('account.token'))
            ->acceptJson()
            // Validačné chyby uvidí koncový zákazník, nech chodia v jeho jazyku.
            ->withHeaders(['Accept-Language' => app()->getLocale()])
            ->timeout(config('account.timeout'))
            ->baseUrl(config('account.url'));
    }

    protected function throwValidationErrors(\Illuminate\Http\Client\Response $response): void
    {
        if ($response->status() === 422) {
            throw ValidationException::withMessages($response->json('errors', []));
        }
    }
}
