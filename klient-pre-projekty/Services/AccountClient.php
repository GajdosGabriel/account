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
     | Komunikácia s klientmi
     |---------------------------------------------------------------*/

    /**
     * Odošle e-mail klientovi cez schránku projektu v Accounte.
     *
     * Odpoveď klienta príde späť webhookom `message.received` s rovnakým
     * `ref`. Zlyhanie sa vyhadzuje – volaj z fronty (notifikácia
     * s ShouldQueue), nech sa pri výpadku Accountu skúsi znova.
     *
     * Blokovaná adresa (nedoručiteľná, odhlásená) vyhodí
     * AccountMessageRejected s dôvodom `suppressed` – tú už neopakuj.
     *
     * @param  array{
     *     to: string, subject: string, html?: string, text?: string, to_name?: string,
     *     ref: array{type: string, id: string|int, label: string, url?: string},
     *     kind?: string, organization_id?: string, mailbox?: string, conversation?: string,
     *     meta?: array<string, mixed>, attachments?: array<int, array{filename: string, mime?: string, content: string}>
     * }  $message  `attachments.*.content` je base64
     * @return array{conversation: array<string, mixed>, message: array<string, mixed>}
     */
    public function sendMessage(array $message): array
    {
        $response = $this->request()->timeout(30)->post('/api/v1/messages', $message);

        if ($response->status() === 422 && $response->json('reason')) {
            throw new AccountMessageRejected($response->json('reason'), (string) $response->json('message'));
        }

        $this->throwValidationErrors($response);

        return $response->throw()->json('data');
    }

    /**
     * Konverzácie k veci v projekte (napr. ku kanálu alebo objednávke).
     *
     * @return array<int, array<string, mixed>>
     */
    public function conversations(string $refType, string|int $refId): array
    {
        try {
            return $this->request()
                ->get('/api/v1/conversations', ['ref_type' => $refType, 'ref_id' => (string) $refId])
                ->throw()
                ->json('data');
        } catch (\Throwable $e) {
            Log::warning('Account: konverzacie sa nepodarilo nacitat', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Celé vlákno so správami.
     *
     * @return array<string, mixed>|null
     */
    public function conversation(string $uuid): ?array
    {
        try {
            return $this->request()->get("/api/v1/conversations/{$uuid}")->throw()->json('data');
        } catch (\Throwable $e) {
            Log::warning('Account: konverzaciu sa nepodarilo nacitat', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** Je adresa v tomto projekte blokovaná (nedoručiteľná, odhlásená)? */
    public function isSuppressed(string $email): bool
    {
        try {
            return (bool) $this->request()->get('/api/v1/suppressions/'.rawurlencode($email))
                ->throw()
                ->json('data.suppressed');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Odhlásenie z e-mailov projektu – Account na adresu prestane posielať. */
    public function suppress(string $email, string $reason = 'unsubscribed', ?string $note = null): void
    {
        $this->request()->post('/api/v1/suppressions', array_filter([
            'email' => $email, 'reason' => $reason, 'note' => $note,
        ]))->throw();
    }

    /** Adresa je znova v poriadku (klient ju opravil). */
    public function unsuppress(string $email): void
    {
        $this->request()->delete('/api/v1/suppressions/'.rawurlencode($email))->throw();
    }

    /* ---------------------------------------------------------------
     | Emailing (kampane sú v Accounte, projekt hlási udalosti)
     |---------------------------------------------------------------*/

    /**
     * Nastala udalosť – aktívne automatizácie v Accounte zaradia maily.
     * Volaj z fronty (ReportMailingEvent), nie priamo v požiadavke.
     *
     * @param  array{type: string, id: string|int, label?: string, url?: string}|null  $ref
     * @param  array<string, scalar|null>  $variables  dosadia sa do textu ako {kluc}
     * @return int počet zaradených mailov
     */
    public function mailingEvent(
        string $event,
        string $email,
        ?array $ref = null,
        array $variables = [],
        ?string $dedupeKey = null,
        ?\DateTimeInterface $occurredAt = null,
        ?string $name = null,
        ?string $threadId = null,
    ): int {
        return (int) $this->request()->post('/api/v1/mailing/events', array_filter([
            'event' => $event,
            'email' => $email,
            'name' => $name,
            'ref' => $ref,
            'variables' => $variables ?: null,
            'dedupe_key' => $dedupeKey,
            'occurred_at' => $occurredAt?->format(DATE_ATOM),
            'thread_id' => $threadId,
        ], fn ($v) => $v !== null))->throw()->json('data.queued');
    }

    /**
     * Vec sa zrušila (storno) – čakajúce maily k nej sa neodošlú, okrem
     * kampaní na udalosti v `$except`.
     *
     * @param  array<int, string>  $except
     */
    public function cancelMailing(string $refType, string|int $refId, array $except = []): int
    {
        return (int) $this->request()->post('/api/v1/mailing/events/cancel', [
            'ref' => ['type' => $refType, 'id' => (string) $refId],
            'except' => $except,
        ])->throw()->json('data.skipped');
    }

    /**
     * Udalosti, ktoré projekt hlási – v Accounte z nich operátor vyberá
     * spúšťač automatizácie. Celý zoznam nahradí doterajší.
     *
     * @param  array<int, array{key: string, label: string, short_label?: string, variables?: array<int, string>, quote_kind?: string}>  $triggers
     */
    public function registerMailingTriggers(array $triggers): void
    {
        $this->request()->put('/api/v1/mailing/triggers', ['triggers' => $triggers])->throw();
    }

    /**
     * Kontakty so súhlasom so zasielaním ponúk (max. 1000 naraz).
     * Odhláseného človeka to späť neprihlási.
     *
     * @param  array<int, array{email: string, name?: string, permission_note: string, permission_at?: string, organization_id?: string}>  $contacts
     * @return array{added: int, updated: int}
     */
    public function upsertMailingContacts(array $contacts): array
    {
        return $this->request()->post('/api/v1/mailing/contacts', ['contacts' => $contacts])->throw()->json('data');
    }

    /** @return array<string, mixed>|null stav kontaktu (odhlásený, nedoručiteľný…) */
    public function mailingContact(string $email): ?array
    {
        try {
            return $this->request()->get('/api/v1/mailing/contacts/'.rawurlencode($email))->throw()->json('data');
        } catch (\Throwable $e) {
            Log::warning('Account: kontakt emailingu sa nepodarilo nacitat', ['error' => $e->getMessage()]);

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
