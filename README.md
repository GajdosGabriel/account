# Account — centrálna správa organizácií, predplatného a limitov

Back-office služba pre tvoje projekty. Firemné údaje, plány, limity
a fakturáciu má na starosti táto aplikácia. Používatelia a heslá zostávajú
v jednotlivých projektoch.

**Stack:** Laravel 13 · Inertia.js · Vue 3 · Tailwind 4 · MySQL
Bez extra PHP rozšírení — beží na základnej inštalácii PHP 8.3.

---

## Deľba práce

| | Account | Projekt |
|---|---|---|
| Používatelia, heslá, prihlásenie | — | **áno** |
| Firemné údaje (IČO, DIČ, adresa) | **áno** | len `organization_id` |
| Plány, ceny, predplatné | **áno** | — |
| Katalóg funkcií a limitov | **áno** (definuje) | **áno** (vynucuje) |
| Vlastné dáta aplikácie | — | **áno** |

Kľúčové pravidlo pre limity:

> Account povie, **aký je limit**. Projekt si spočíta svoje dáta a limit **vynúti**.

Account totiž do dát projektu nevidí — a ani nemá.

---

## Spustenie

```powershell
composer install
npm install

php artisan migrate:fresh --seed
php artisan serve      # a v druhom okne: npm run dev
```

Databáza musí existovať:

```sql
CREATE DATABASE account CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Prihlásenie podľa `SEED_ADMIN_EMAIL` a `SEED_ADMIN_PASSWORD` v `.env`.
Seed vypíše aj service tokeny pre tri ukážkové projekty — zobrazia sa iba raz.

---

## Ako to spolupracuje

```
projekt1.local                     account.local
──────────────                     ─────────────
prihlásenie (vlastné)
      │
      ├─ GET  /api/v1/organizations/{uuid}/entitlements
      │        → { access, read_only, features, usage, over_limit }
      │        cache 5 min, pri výpadku posledná známa hodnota
      │
      ├─ PUT  /api/v1/organizations/{uuid}      ← formulár v projekte
      │        → 422 s chybami pri poliach
      │
      ├─ POST /api/v1/organizations             ← nájdi alebo vytvor podľa IČO
      │
      └─ POST /api/v1/organizations/{uuid}/usage  ← denné hlásenie spotreby
                                            ↓
                              webhook späť do projektu
                              (zruší cache pri zmene)
```

Hotový kód pre stranu projektu je v priečinku **[klient-pre-projekty/](klient-pre-projekty/)**.

---

## Firemné údaje

Organizácia drží všetko, čo treba na slovenskú faktúru aj pre eshop:

| Skupina | Polia |
|---|---|
| Identifikácia | názov, obchodné meno, právna forma, IČO, DIČ, IČ DPH, vzťah k DPH, OSS |
| Zápis v registri | súd/úrad, oddiel, vložka, dátum vzniku — **povinné v pätičke faktúry** |
| Sídlo | ulica + číslo zvlášť, PSČ, mesto, kraj, krajina |
| Kontakt | všeobecný e-mail, e-mail na faktúry, telefón, web |
| Banka | názov banky, IBAN, SWIFT |
| Fakturácia | mena, splatnosť, spôsob platby, jazyk, doručovanie, naše číslo u zákazníka |

**Vzťah k DPH** rozlišuje neplatiteľa, platiteľa podľa § 4 a registráciu
podľa § 7 alebo § 7a. Tie posledné dve majú IČ DPH, ale nie sú platitelia —
formulár sa preto na IČ DPH pýta len vtedy, keď dáva zmysel.

### Adresy

Sídlo je priamo na organizácii, lebo je vždy práve jedno a fakturuje sa naň.
Ostatné adresy sú vo vlastnej tabuľke, pretože eshop ich potrebuje viac:

| Typ | Na čo |
|---|---|
| `mailing` | adresa na zasielanie pošty, keď sa líši od sídla |
| `delivery` | dodacia adresa — sklady, predajne, viac naraz |
| `branch` | prevádzkareň |

Každá má príjemcu (na obálke býva iný než názov firmy), telefón a poznámku
pre kuriéra. `mailingLines()` vráti riadky na obálku — buď z poštovej adresy,
alebo zo sídla, ak žiadna nie je.

### Kontaktné osoby

Samostatná tabuľka: štatutár, fakturácia, technický kontakt. **Nie sú to
používatelia** — tí zostávajú v projektoch. Toto je telefónny zoznam.

### Čo chýba na faktúru

`missingBillingFields()` vráti zoznam chýbajúcich údajov a API ho posiela
v `billing.missing`. Projekt tak vie zákazníka upozorniť skôr, než sa
faktúra pokazí.

---

## Katalóg funkcií

Každý projekt má vlastný katalóg toho, čo vôbec vie merať a obmedzovať.
Bez neho by bol `plans.features` voľný JSON, kde preklep v kľúči znamená,
že projekt limit nenájde a pustí neobmedzene.

| Typ | Hodnota | Príklad |
|---|---|---|
| `limit` | číslo, `null` = neobmedzene | `max_records: 500` |
| `flag` | `true` / `false` | `export: true` |

Limit sa páruje so **spotrebou** cez pole *metrika*: funkcia `max_records`
+ metrika `records`. Projekt potom posiela `{"records": 460}` a Account vie
povedať „460 z 500".

Spravuje sa v **Projekty → detail projektu**.

---

## Odpoveď entitlements

```json
{
  "product": "projekt-1",
  "plan": "standard",
  "status": "active",
  "access": true,
  "read_only": false,
  "features":   { "max_records": 500, "export": true },
  "usage":      { "records": 460 },
  "over_limit": {},
  "current_period_end": "2026-09-05T00:00:00+02:00"
}
```

Po znížení plánu, keď má zákazník viac dát než nový limit dovoľuje:

```json
"features":   { "max_records": 10 },
"over_limit": { "max_records": { "limit": 10, "used": 15 } }
```

Projekt vtedy ukáže „máte 15 z 10, nové už nepridáte". **Nikdy nemaž
ani nezamykaj existujúce dáta** — najrýchlejšia cesta k vráteniu peňazí.

---

## Životný cyklus predplatného

```
trialing ──> active ──> past_due ──> suspended ──> cancelled
                ^          │             │
                └──────────┴─────────────┘   (po zaplatení)
```

| Stav | Čo dostane projekt | Trvanie |
|---|---|---|
| `active` | `access: true` | — |
| `past_due` | `access: true` — **plný prístup** | `ACCOUNTS_GRACE_DAYS` (14) |
| `suspended` | `read_only: true`, funkcie vypnuté | `ACCOUNTS_SUSPENDED_DAYS` (30) |
| `cancelled` | `access: false` | — |

Prechody vynucuje `SubscriptionStatus::canTransitionTo()`, takže sa nedá
skočiť z `active` rovno do `suspended`.

```powershell
php artisan subscriptions:lifecycle   # denne o 03:00
php artisan webhooks:retry            # kazdych 5 minut
```

Na produkcii stačí jediný cron:

```
* * * * * cd /cesta/k/account && php artisan schedule:run >> /dev/null 2>&1
```

---

## API

```
GET  /api/v1/organizations                      zoznam firiem projektu
GET  /api/v1/organizations/{uuid}               detail vrátane adries a kontaktov
POST /api/v1/organizations                      nájdi podľa IČO alebo vytvor
PUT  /api/v1/organizations/{uuid}               úprava z formulára projektu
POST /api/v1/organizations/lookup               vyhľadanie v registri RPO
GET  /api/v1/organizations/{uuid}/entitlements  limity a stav
POST /api/v1/organizations/{uuid}/usage         hlásenie spotreby

Authorization: Bearer acc_xxxxxxxx
```

Token je viazaný na projekt, takže projekt 1 sa k firmám ani limitom
projektu 2 nedostane. Nový token:

```powershell
php artisan accounts:issue-token projekt-1 "produkcny server"
```

---

## Webhooky

| Udalosť | Kedy | Čo má projekt spraviť |
|---|---|---|
| `organization.created` | vznik novej firmy | naviazať sa na ňu, ak ju pozná podľa IČO |
| `organization.updated` | zmena firemných údajov | zrušiť cache |
| `organization.deleted` | zmazanie firmy | zrušiť cache |
| `subscription.status_changed` | zmena stavu predplatného | zrušiť cache entitlements |

Podpis: `HMAC-SHA256` z `timestamp.telo`, hlavičky `X-Accounts-Signature`
a `X-Accounts-Timestamp`. Neúspešné doručenie sa opakuje 8× s exponenciálnym
backoffom (30 s → 12 h).

---

## Overovanie firemných údajov

- **IČO** — kontrolná číslica (mod 11) a vyhľadanie v registri RPO
- **IČ DPH** — VIES, vrátane podkladu pre prenesenie daňovej povinnosti

Oboje je odolné voči výpadku registra — formulár funguje aj keď register nebeží.

---

## Stripe

`POST /api/webhooks/stripe`, podpis overujeme ručne cez `hash_hmac`,
takže netreba `stripe-php`.

| Stripe | Prechod |
|---|---|
| `invoice.payment_succeeded` | → `active` |
| `invoice.payment_failed` | → `past_due` |
| `customer.subscription.deleted` | → `cancelled` |

Stripe faktúry nespĺňajú náležitosti slovenskej faktúry (IČO/DIČ/IČ DPH),
preto sa doklady vystavujú tu — viď kapitolu Fakturácia nižšie.

---

## Fakturácia

Kompletné vystavovanie dokladov podľa slovenských zvyklostí. Nič externé,
žiadna SuperFaktúra.

### Dve pravidlá, ktoré držia všetko ostatné

1. **Koncept sa mení, vystavený doklad nikdy.** Číslo sa prideľuje až pri
   vystavení, obsah sa vtedy zamkne a opravuje sa výhradne dobropisom.
2. **Fakturačné údaje sa v okamihu vystavenia odfotia** do
   `billing_snapshot` a `supplier_snapshot`. Keď si zákazník o rok zmení
   sídlo, stará faktúra sa nezmení.

Obe pravidlá vynucuje `InvoicePolicy` — a tá istá policy skladá aj položky
v dropdown menu vo frontende. Tlačidlo, ktoré by server odmietol, sa
nezobrazí.

### Typy dokladov

| Typ | Číselný rad | Poznámka |
|---|---|---|
| Faktúra | `2026NNNN` | daňový doklad |
| Zálohová faktúra | `20269NNNN` | nie je daňový doklad, prevedie sa na riadnu |
| Dobropis | `20268NNNN` | záporné sumy, väzba na pôvodnú faktúru |

Číslo sa berie zo zamknutého riadku číselného radu (`SELECT … FOR UPDATE`),
nikdy z `MAX(number)` — inak by pri dvoch súbežných požiadavkách vznikli
duplicity. Rad sa na začiatku roka lenivo reštartuje na jednotku.

### DPH

`VatResolver` rozhodne sadzbu aj zákonný text podľa krajiny a IČ DPH:

| Situácia | Sadzba | Na faktúre |
|---|---|---|
| SK odberateľ | 23 % | bežná faktúra |
| EÚ firma s IČ DPH | 0 % | prenesenie daňovej povinnosti, § 15 ods. 1 |
| EÚ osoba bez IČ DPH | 23 % / OSS | podľa registrácie v OSS |
| Mimo EÚ | 0 % | miesto dodania mimo SR |
| Neplatiteľ (dodávateľ) | 0 % | „Nie sme platiteľmi DPH.“ |

### QR platba

Na faktúre aj v e-maile je **PAY by square** — ten štvorec, ktorý naskenuješ
v appke banky a máš predvyplnený príkaz. Číta ho VÚB, Tatra banka, SLSP,
ČSOB, mBank aj 365.bank.

Štandard vyžaduje LZMA kompresiu, ktorú PHP v jadre nemá. Namiesto ťahania
binárnej extenzie je v `app/Services/Invoicing/Qr/LzmaEncoder.php` vlastný
range coder, ktorý emituje len literály — výsledok je plne dekódovateľný
stream, len o percento väčší. Pri 150 bajtoch je to jedno.

Pre zahraničie sa dá prepnúť na EPC QR (`INVOICE_QR_FORMAT=epc`).

### Príkazy

```powershell
php artisan invoices:generate            # koncepty na obdobia, ktoré končia
php artisan invoices:generate --issue    # rovno vystaviť
php artisan invoices:generate --send     # vystaviť a odoslať
php artisan invoices:generate --dry-run

php artisan invoices:remind              # upomienky po splatnosti
php artisan invoices:remind --dry-run
```

Obe bežia v scheduleri (`routes/console.php`). Predvolene vzniká iba
**koncept** — faktúra, ktorá odíde zákazníkovi bez toho, aby sa na ňu
niekto pozrel, je najrýchlejšia cesta k trápnemu dobropisu.

Upomienky sa stupňujú: 3 dni zdvorilo, 10 dní dôraznejšie, 21 dní posledná
výzva. Nikdy dve v jeden deň, nikdy tá istá úroveň dvakrát.

### Export pre účtovníka

`/invoices/export?format=csv|xml&from=…&to=…` — CSV s BOM (Excel na Windows
nerozbije diakritiku) alebo štruktúrované XML.

### Nastavenie

Údaje dodávateľa sú v `config/invoicing.php`, prepínajú sa cez `.env`
(viď `.env.example`, blok *Fakturacia*). Minimum: `INVOICE_SUPPLIER_*`,
IBAN a IČ DPH.

Pre PDF a QR treba dve knižnice:

```powershell
composer require barryvdh/laravel-dompdf bacon/bacon-qr-code
```

Bez nich aplikácia funguje ďalej, len PDF a QR sa nevygenerujú — detail
dokladu na to upozorní.

---

## Soft delete a kôš

Mäkko sa mažú entity, ktorých zmazanie môže byť omyl: firmy, projekty,
plány, doklady, kontakty, tokeny, webhooky. Vo filtri zoznamu je *Kôš*
a v menu položky *Obnoviť*.

Zámerne sa **nemažú mäkko** `audit_logs`, `subscription_events`,
`invoice_events`, `webhook_deliveries` a `usage_reports` — mazateľný audit
log nie je audit log a technické záznamy sa čistia dávkovo.

Vystavený doklad sa nedá zmazať natrvalo ani po presune do koša. Archivačná
povinnosť je desať rokov, nie preferencia.

---

## Jazyky

Validačné chyby z API vidí **koncový zákazník v projekte**, nie ty. Jazyk sa
preto riadi ním:

```
POST /api/v1/organizations
Accept-Language: de-DE,de;q=0.9      → nemecké hlášky
POST /api/v1/organizations?lang=cs   → české (parameter prebije hlavičku)
```

Podporované: **sk** (predvolený), **cs**, **de**, **en**. Zoznam je
v `config/accounts.php` → `locales`, preklady v `lang/{kod}/`.

| Súbor | Obsah |
|---|---|
| `lang/{kod}/validation.php` | validačné hlášky, názvy polí, vlastné pravidlá |
| `lang/{kod}/messages.php` | chyby API, registrov a stavov predplatného |

Vlastné pravidlá majú kľúče `validation.ico_checksum`, `validation.vat_format`,
`validation.vat_country` a `validation.vat_vies`.

Názvy polí sú v sekcii `attributes`, takže hláška znie „Pole IČO je povinné",
nie „Pole ico je povinné".

Odpovede z registrov RPO a VIES sa cachujú **len ak sú definitívne**. Chybové
hlášky sú preložené, takže by sa inak zamrazil jazyk prvého volajúceho.

Projekt si jazyk drží u seba a posiela ho pri každom volaní:

```php
Http::withToken(config('account.token'))
    ->withHeaders(['Accept-Language' => app()->getLocale()])
    ->put("/api/v1/organizations/{$uuid}", $data);
```

---

## Testy

```powershell
php artisan test
```

Pokryté: skladanie limitov z katalógu a plánu, `null` = neobmedzene,
prekročenie limitu, pozastavené predplatné, deduplikácia podľa IČO,
izolácia medzi projektmi, kontrolná číslica IČO a preklady chýb.

Fakturácia (`InvoicingTest`, `PayBySquareTest`): neprerušený číselný rad,
reštart v novom roku, zmazaný koncept nespotrebuje číslo, zaokrúhľovanie
DPH a rekapitulácia sadzieb, reverse charge a vývoz mimo EÚ, nemennosť
vystaveného dokladu, snapshot fakturačných údajov, čiastočné úhrady,
dobropis so zápornými sumami, prevod zálohovej faktúry na riadnu,
policy pravidlá a kôš.

`PayBySquareTest` overí LZMA stream skutočným dekodérom (`xz`), ak je
v systéme — inak sa ten jeden test preskočí.

---

## Štruktúra

```
app/
├── Enums/
│   ├── SubscriptionStatus.php        povolené prechody stavov
│   └── InvoiceType|Status.php        typy dokladov a ich životný cyklus
├── Policies/InvoicePolicy.php        zákonné pravidlá pre doklady = aj UI menu
├── Services/
│   ├── Billing/SubscriptionManager   stavový automat
│   ├── Entitlements/                 čo kto smie + porovnanie so spotrebou
│   ├── Usage/UsageRecorder           príjem spotreby, kto je blízko limitu
│   ├── Registry/                     RPO (IČO) a VIES (IČ DPH)
│   ├── Webhooks/                     odosielanie s HMAC podpisom
│   └── Invoicing/
│       ├── InvoiceService            vystavenie, úhrady, storno, dobropis
│       ├── InvoiceNumberGenerator    číselné rady bez dier
│       ├── VatResolver               sadzba + zákonný text podľa krajiny
│       ├── InvoiceRenderer           HTML náhľad aj PDF z jednej šablóny
│       ├── InvoiceMailer             odoslanie a upomienky
│       └── Qr/                       PAY by square vrátane vlastného LZMA
├── Http/Controllers/Api/             server-to-server endpointy
└── Models/ProductFeature.php         katalóg funkcií

resources/views/invoices/document.blade.php   faktúra (HTML aj PDF)
resources/views/emails/                       e-maily s dokladmi
resources/js/Components/DropdownMenu.vue      menu riadené policy
resources/js/Composables/useInvoiceActions.js jedno miesto s akciami dokladu

klient-pre-projekty/                  kód na skopírovanie do projektov
resources/js/Pages/                   back-office UI
```

---

## Ďalšie kroky

1. **Upozornenia pri 80 % limitu** — dashboard to už ukazuje, chýba e-mail.
2. **Účtovanie nadspotreby** — `usage_reports` na to už dáta má; stačí
   pridať položku do faktúry cez `InvoiceService::addItem`.
3. **História spotreby** — dnes držíme len poslednú hodnotu; na grafy by
   bolo treba dennú snímku.
4. **Párovanie platieb z výpisu** — `variable_symbol` je na to pripravený,
   chýba import bankového výpisu (CAMT.053 alebo CSV).
5. **Kontrolný výkaz DPH** — export už dáta má, treba doplniť formát A1/A2.
