# Account a pripojené projekty

Prečo tokeny, ktoré vypíše seeder, naozaj fungujú – a napriek tomu sa na nich nedá stavať
produkcia. Od základov, krok po kroku.

**Obsah**

1. [Rozpor, ktorý nesedí](#1-rozpor-ktorý-nesedí)
2. [Kto je kto: Account verzus projekty](#2-kto-je-kto-account-verzus-projekty)
3. [Produkt, funkcia, plán, predplatné](#3-produkt-funkcia-plán-predplatné)
4. [Čo je token a ktorým smerom tečie](#4-čo-je-token-a-ktorým-smerom-tečie)
5. [Tri druhy tokenov – jadro veci](#5-tri-druhy-tokenov--jadro-veci)
6. [Čo presne spraví migrate:fresh](#6-čo-presne-spraví-migratefresh)
7. [Kolízia pri premenovaní projektov](#7-kolízia-pri-premenovaní-projektov)
8. [Opačný smer: webhooky](#8-opačný-smer-webhooky)
9. [Čo sa stane, keď Account nebeží](#9-čo-sa-stane-keď-account-nebeží)
10. [Čo spraviť](#10-čo-spraviť)

---

## 1. Rozpor, ktorý nesedí

Seeder vypíše po nasadení na produkcii niečo takéto:

```
Event: acc_CBcAWM4SW7sBMmeD8BPpx1YJOY2CBqua0RIqTcw56rIDdOhg
Anonymizer: acc_rok56FnqopGzewszwF6tCju922sW4KElSjXEp0uch6yGwufJ
Samospráva: acc_BvrjMdhkyHIGf7kK62vDFAZbfprfnczg5jfL2dxBm8gPP4Wv
```

Tie tokeny **sú z produkcie a fungujú**. Problém nie je, či platia. Problém je,
**ako dlho** budú platiť – vytvoril ich demo seeder a najbližší `migrate:fresh --seed`
ich zahodí a vyrobí tri úplne nové.

Predstav si heslo na wifi, ktoré si router vygeneruje sám pri každom resete do továrenských
nastavení. Heslo je platné, pripojíš sa s ním, všetko ide. Lenže keď router raz resetuješ,
staré heslo prestane platiť a musíš ho prepísať v každom telefóne v dome. Nikto by na takom
hesle nestaval domácnosť – dá si vlastné, ktoré si zvolí on, nie router.

Presne to je rozdiel medzi **tokenom, ktorý si vymyslel seeder**, a **tokenom, ktorý určíš
ty v súbore `.env`**.

---

## 2. Kto je kto: Account verzus projekty

Sú tu dva odlišné druhy aplikácií a je kľúčové ich nemiešať.

**Account** je centrálna evidencia. Vie, aké firmy existujú, aké majú IČO a fakturačné
údaje, aký plán si kúpili, či zaplatili a aké faktúry dostali. Je to jediné miesto pravdy
o peniazoch a predplatnom.

**Projekty** (Event, Anonymizer, Samospráva) sú samostatné aplikácie s vlastnou databázou,
vlastným prihlasovaním a vlastnými dátami. Account do nich nevidí a ani nemá – v
[routes/api.php](../routes/api.php) to je napísané priamo v komentári: používatelia a heslá
tu nie sú, tie zostávajú v projektoch.

Vzťah medzi nimi má presne dva smery a nič iné:

| Smer | Kto volá | Čo posiela |
|---|---|---|
| Projekt → Account | Event, Anonymizer, Samospráva | `Authorization: Bearer acc_…` – otázka „smie táto firma?" |
| Account → Projekt | Account | webhook s podpisom – oznámenie „zmenilo sa predplatné" |

Projekt sa nikdy nepýta „koľko stojí Standard". Pýta sa **„smie táto firma pokračovať a aké
má limity?"** Odpoveď si spracuje sám – Account nevie a nemá vedieť, koľko záznamov má
konkrétny zákazník v Evente.

---

## 3. Produkt, funkcia, plán, predplatné

V Accounte sa točia štyri pojmy. Keď si ich raz usadíš, zvyšok je ľahký.

| Pojem | Tabuľka | Čo to je v ľudskej reči |
|---|---|---|
| **Produkt** | `products` | Jeden pripojený projekt. Má kľúč (`event`), názov a URL. Kľúč je technický identifikátor, názov je len to, čo vidíš v administrácii. |
| **Funkcia** | `product_features` | Čo sa v danom projekte dá zapnúť alebo merať. Dva typy: *flag* (áno/nie, napríklad „Export do XLSX") a *limit* (číslo, napríklad „Počet záznamov"). |
| **Plán** | `plans` | Cenníková položka: Free, Standard, Pro. Ku každému plánu je priradená konkrétna hodnota každej funkcie – Standard má 500 záznamov, Pro neobmedzene. |
| **Predplatné** | `subscriptions` | Konkrétna firma má konkrétny plán a nejaký stav: aktívne, v skúšobnej dobe, po splatnosti, zrušené. |

Piaty pojem už nie je tabuľka, ale výsledok výpočtu:

> **Entitlement** – odpoveď na otázku „čo táto firma práve teraz smie". Vznikne tak, že
> Account vezme plán, pripočíta prípadné individuálne výnimky a zohľadní stav predplatného.
> Projekt dostane hotový výsledok a nemusí nič dopočítavať.

Prečo je katalóg funkcií samostatná tabuľka a nie voľný JSON? Lebo preklep v kľúči by bol
tichá katastrofa: projekt by hľadal limit `max_records`, nenašiel by nič – a pustil by
zákazníka neobmedzene zadarmo. Katalóg určuje, aké kľúče vôbec smú existovať a akého sú typu.

---

## 4. Čo je token a ktorým smerom tečie

Tu sa to najčastejšie zamotá, tak veľmi pomaly.

> **Token vydáva Account. Používa ho projekt.** Je to preukaz, ktorým sa projekt Accountu
> predstaví. Nie naopak – Account sa projektu nikdy nepreukazuje tokenom, na to má
> webhookový podpis (kapitola 8).

### Ako vyzerá jedno volanie

1. **Do Eventu sa prihlási používateľ firmy Ukážka s. r. o.**
   Prihlásenie rieši Event sám, Account o ňom nevie.
2. **Middleware v Evente sa spýta: smie táto firma?**
   Zavolá `GET /api/v1/organizations/{uuid}/entitlements` na Account.
3. **Do hlavičky pripojí svoj token.**
   `Authorization: Bearer acc_CBcAWM4…` – hodnota z `ACCOUNT_TOKEN` vo vlastnom `.env`.
4. **Account token overí.**
   Spočíta z neho SHA-256 a hľadá zhodu v tabuľke `service_clients`. Skontroluje, či nie je
   odvolaný, či nevypršal, či má potrebné oprávnenie a či je produkt aktívny.
   Viď [ServiceTokenAuthentication.php](../app/Http/Middleware/ServiceTokenAuthentication.php).
5. **Account vráti entitlements.**
   Napríklad: stav *active*, `max_records = 500`, `export = true`.
6. **Event si odpoveď uloží do cache na päť minút a rozhodne.**
   Limit vynucuje projekt – iba on vidí svoje dáta a vie ich spočítať.

### Prečo sa plain hodnota vypíše len raz

V databáze Accountu **nie je uložený samotný token**, iba jeho SHA-256 odtlačok a prvých
dvanásť znakov na rozlíšenie ([ServiceClient::issue()](../app/Models/ServiceClient.php)).
To je zámer: keby niekto ukradol databázu, tokeny z nej nevytiahne.

Dôsledok je ten, že plain hodnotu vie systém povedať v jedinej sekunde – keď ju vygeneroval.
Preto ju seeder vypíše do konzoly. Ak si ju vtedy neskopíruješ, už ju nikto nezistí a musíš
vydať nový token.

### Token patrí jednému produktu

To nie je detail, to je bezpečnostný základ celého API. Token Eventu patrí produktu `event`,
takže Account z neho vie, kto sa pýta, a vráti iba firmy a limity, ktoré sa Eventu týkajú.
Cez token Eventu sa k dátam Anonymizera nikto nedostane.

Každý token má navyše zoznam oprávnení, ktoré API kontroluje po jednotlivých endpointoch:

| Oprávnenie | Umožní |
|---|---|
| `organizations:read` | Načítať firmy, vyhľadať firmu podľa IČO |
| `organizations:write` | Založiť alebo upraviť firmu |
| `entitlements:read` | Zistiť, čo firma smie |
| `usage:write` | Nahlásiť spotrebu, teda koľko záznamov firma má |

---

## 5. Tri druhy tokenov – jadro veci

Token môže vzniknúť tromi spôsobmi. Vyzerajú rovnako – `acc_` a 48 znakov – ale správajú sa
úplne inak.

| Druh | Kde vzniká | Prežije reseed? | Na čo je |
|---|---|---|---|
| **A. Demo**<br>meno „lokálny vývoj" | Seeder si ho **vymyslí sám** pri každom seedovaní a vypíše do konzoly | **Nie** – vznikne nová náhodná hodnota | Lokálny vývoj |
| **B. Pevný**<br>z `.env` | Hodnotu **určíš ty** v `.env` Accountu, seeder ju už len zaregistruje | **Áno** – pravda je v súbore, nie v databáze | Produkcia |
| **C. Ručný**<br>príkaz alebo administrácia | `accounts:issue-token`, alebo *API a webhooky → Vygenerovať* | **Nie** – žije iba v databáze | Dodatočný token, rýchla výmena |

Tokeny, ktoré vypíše seeder pri `migrate:fresh --seed`, sú **druhu A**. Vidno to podľa toho,
že ich vypísal seeder – a seeder vypisuje iba to, čo práve sám vymyslel.

> **Preto to varovanie.** Keby si ich rozdal do projektov a o mesiac znova spustil
> `migrate:fresh --seed`, tabuľka `service_clients` by sa vymazala a vznikli by tri nové
> hodnoty. Všetky tri projekty by od tej sekundy dostávali `401 Neplatný token` – a ty by si
> hľadal chybu v kóde, ktorý je v poriadku.

### Ako funguje pevný token

Presne toto už v seederi je pre Samosprávu – viď `connectedProjects()` v
[DatabaseSeeder.php](../database/seeders/DatabaseSeeder.php). V `.env` Accountu:

```env
SAMOSPRAVA_URL=https://samosprava.zastavy-vlajky.sk
SAMOSPRAVA_SERVICE_TOKEN=acc_...
```

Seeder si tú hodnotu prečíta a uloží do databázy jej odtlačok. Tú istú hodnotu vložíš do
`.env` projektu Samospráva:

```env
ACCOUNT_URL=https://account.zastavy-vlajky.sk
ACCOUNT_TOKEN=acc_...            # tá istá hodnota
ACCOUNT_WEBHOOK_SECRET=whsec_...
```

Odteraz je zdrojom pravdy súbor `.env`, nie databáza. Databázu môžeš premazať koľkokrát
chceš – po seede sa ten istý token zaregistruje znova a projekt sa pripojí, akoby sa nič
nestalo. **To je celé kúzlo.**

Novú náhodnú hodnotu vygeneruješ napríklad takto a vložíš ju na obe strany:

```bash
php -r "echo 'acc_'.bin2hex(random_bytes(24)).PHP_EOL;"
```

---

## 6. Čo presne spraví `migrate:fresh`

Tento príkaz neznamená „aktualizuj databázu". Znamená **zbúraj a postav odznova**. Vidno to
na prvom riadku výpisu: `Creating migration table` – teda aj evidencia migrácií vzniká od
nuly, lebo predtým bolo všetko zmazané.

1. **Zahodí všetky tabuľky v databáze.** Nielen tie s projektmi. Aj firmy, faktúry,
   predplatné, používateľov, audit log.
2. **Spustí všetky migrácie od začiatku.** Vznikne prázdna, ale správne poskladaná databáza.
3. **Spustí seedery.** Najprv `AdminUserSeeder`, potom produkty a demo dáta, nakoniec
   `InvoiceSeeder`.

> **Dôsledok na produkcii.** Po takomto behu sedia v databáze **demo dáta**: firma
> Ukážka s. r. o., Malá firma a jedenásť ukážkových faktúr vrátane dobropisu a storna. Ak tam
> predtým boli skutočné dáta, sú preč – a bez zálohy sa vrátiť nedajú.

Pri bežnom nasadení novej verzie sa `migrate:fresh` nikdy nepoužíva. Používa sa:

```bash
php artisan migrate --force
```

Ten iba dobehne migrácie, ktoré ešte nebežali, a existujúcich dát sa nedotkne. Prepínač
`--force` je tam preto, že v produkčnom prostredí sa Laravel na každý zásah do databázy pýta
a v neinteraktívnom režime radšej neurobí nič.

---

## 7. Kolízia pri premenovaní projektov

Seeder má dve rôzne časti, ktoré vytvárajú produkty:

- `products()` – demo projekty aj s plánmi a limitmi; každému, kto ešte nemá žiadny token,
  jeden vydá,
- `connectedProjects()` – skutočne pripojené projekty s pevným tokenom z `.env`.

Kým sa tretí demo projekt volal `projekt-3`, boli to dva nezávislé riadky v tabuľke. Po
premenovaní na `samosprava` to bol zrazu **ten istý produkt** – a poradie zabolelo:

1. `products()` vytvorí produkt `samosprava`. Nemá žiadny token, tak mu jeden vydá – ten
   náhodný demo.
2. `connectedProjects()` sa pozrie na ten istý produkt. Má poistku „ak už neodvolaný token
   existuje, nerob nič" – aby pri opakovanom seede nevznikali duplikáty.
3. Poistka zaberie a funkcia skončí. Pevný token zo `SAMOSPRAVA_SERVICE_TOKEN` sa **nikdy
   nezaregistruje**.

Reálna Samospráva by sa teda voči API neprihlásila, hoci má v `.env` správnu hodnotu. Oprava
je jeden zoznam a jedna podmienka navyše – produkty s pevným tokenom sa v demo časti
preskočia:

```php
protected const CONNECTED_KEYS = ['samosprava'];

if (! in_array($definition['key'], self::CONNECTED_KEYS, true)
    && $product->serviceClients()->count() === 0) {
    // demo token vydáme len projektom, ktoré nemajú pevný
}
```

Po tejto oprave vypíše seeder demo tokeny už len pre Event a Anonymizer. Samospráva si vezme
ten z `.env`.

---

## 8. Opačný smer: webhooky

Keď sa v Accounte niečo zmení – firma si upraví adresu, predplatné spadne do stavu po
splatnosti – Account to musí projektom oznámiť. Inak by projekty ešte päť minút pracovali so
starou cache.

1. **V Accounte nastane udalosť**, napríklad `subscription.status_changed`.
2. **Zapíše sa doručenie do `webhook_deliveries`.** Najprv záznam, až potom odoslanie – aby
   existovala história a dalo sa opakovať.
3. **Fronta pošle HTTP požiadavku na URL projektu**, podpísanú tajomstvom z
   `webhook_endpoints.secret`, ktoré projekt pozná ako `ACCOUNT_WEBHOOK_SECRET`.
4. **Projekt overí podpis a zahodí svoju cache.** Pri ďalšom requeste si vypýta čerstvé
   entitlements.

> **Dva rôzne tajné údaje.** `ACCOUNT_TOKEN` preukazuje *projekt Accountu* pri odchádzajúcich
> volaniach. `ACCOUNT_WEBHOOK_SECRET` overuje, že prichádzajúci webhook naozaj poslal
> *Account*. Sú to dve nezávislé hodnoty a nesmú sa zamieňať.

---

## 9. Čo sa stane, keď Account nebeží

Pravidlo znie: **čítanie degraduje elegantne, zápis nie.**

| Situácia | Správanie projektu |
|---|---|
| Account odpovedá | Entitlements sa uložia do cache na päť minút. |
| Account nebeží, cache je platná | Použije sa posledná známa hodnota, používateľ nič nespozoruje. |
| Account nebeží dlhšie ako sedem dní | Projekt prepne do režimu iba na čítanie, aby výpadok neznamenal neobmedzený prístup zadarmo. |
| Projekt Account nikdy nedosiahol | Radšej pustí dnu, než by zamkol platiaceho zákazníka kvôli sieti. |
| Predplatné nie je aktívne | Presmerovanie na platobnú stránku, nie strohé 403. |

Zápisy do Accountu naopak pri výpadku poctivo zlyhajú. Tichá fronta by vytvorila konflikty
v dátach a dve verzie tej istej firmy sa potom zlučujú veľmi ťažko.

---

## 10. Čo spraviť

1. **Rozhodnúť, či majú byť na produkcii demo dáta.** Ak má produkcia ostať čistá, treba
   oddeliť demo seeder od produkčného.
2. **Pre každý reálny projekt zaviesť pevný token.** Dnes ho má iba Samospráva. Ak sú Event
   a Anonymizer tiež nasadené, potrebujú to isté – inak im každý reseed zabije pripojenie.
3. **Vložiť hodnoty do `.env` na oboch stranách.** Do Accountu `*_SERVICE_TOKEN`, do projektu
   tú istú hodnotu ako `ACCOUNT_TOKEN`. Plus `ACCOUNT_URL` a `ACCOUNT_WEBHOOK_SECRET`.
4. **Na produkcii už nikdy `migrate:fresh`.** Pri nasadení novej verzie stačí
   `php artisan migrate --force`.
5. **Robiť zálohu pred každým zásahom do databázy.** Jeden `mysqldump` stojí sekundy.

> **Zhrnutie jednou vetou.** Token, ktorý si vymyslel seeder, je dobrý na vývojovom počítači.
> Token, ktorý si určíš sám a napíšeš do `.env`, je jediný, na ktorom sa dá postaviť
> produkcia – lebo prežije aj úplné premazanie databázy.
