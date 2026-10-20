# Klient pre projekty

Súbory na skopírovanie do každého z tvojich projektov. Nič sa neinštaluje
cez Composer – je to obyčajný kód, ktorý si prispôsobíš.

## Kam čo patrí

| Súbor tu | Kam v projekte |
|---|---|
| `config/account.php` | `config/account.php` |
| `Services/AccountClient.php` | `app/Services/AccountClient.php` |
| `Services/Entitlements.php` | `app/Support/Entitlements.php` |
| `Http/Middleware/CheckAccountEntitlements.php` | `app/Http/Middleware/` |
| `Http/OrganizationProxyController.php` | `app/Http/Controllers/` |
| `Http/AccountWebhookController.php` | `app/Http/Controllers/` |
| `ReportUsage.php` | `app/Console/Commands/` |
| `Services/AccountMessageRejected.php` | `app/Services/` |
| `Notifications/AccountMailChannel.php` | `app/Notifications/` |
| `Events/AccountMessageEvent.php` | `app/Events/` |
| `Jobs/ReportMailingEvent.php` | `app/Jobs/` |

## Nastavenie

```env
ACCOUNT_URL=http://account.local
ACCOUNT_TOKEN=acc_xxxxxxxxxxxxxxxxxxxx
ACCOUNT_WEBHOOK_SECRET=whsec_xxxxxxxx
```

Token vygeneruješ v Accounte: **API a webhooky → Vygenerovať**, alebo

```bash
php artisan accounts:issue-token projekt-1 "produkcny server"
```

```php
// bootstrap/app.php v projekte
$middleware->alias([
    'entitlements' => \App\Http\Middleware\CheckAccountEntitlements::class,
]);
```

```php
// routes/web.php v projekte
Route::middleware(['auth', 'entitlements'])->group(function () {
    // celá aplikácia
});

Route::get('/predplatne', fn () => view('billing.locked'))->name('billing.locked');
Route::post('/webhooks/account', AccountWebhookController::class)
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
```

## Vynucovanie limitov

Limit vynucuje projekt – iba on vidí svoje dáta. Account povie, aký limit platí.

```php
$entitlements = request()->attributes->get('entitlements');

if ($entitlements->exceeded('max_records', Zaznam::count(), adding: 1)) {
    return back()->with('error', 'Dosiahli ste limit plánu. Zvýšte ho v nastaveniach predplatného.');
}

if (! $entitlements->can('export')) {
    abort(403, 'Export je dostupný od vyššieho plánu.');
}
```

**Vynucuj iba pri vytváraní nového.** Nikdy nemaž ani nezamykaj existujúce dáta
pri znížení plánu – zákazník má vidieť „máte 15 z 10, nové už nepridáte“,
nie stratiť prístup k vlastnej práci.

```blade
@if ($entitlements->overLimit())
    <div class="alert">
        Ste nad limitom plánu. Existujúce dáta zostávajú, nové sa nedajú pridať.
    </div>
@endif
```

## Komunikácia s klientmi (e-maily cez Account)

Mail, na ktorý čakáš odpoveď, pošli cez Account. Odpoveď ti príde webhookom a operátor ju
uvidí v spoločnom Inboxe. Token potrebuje `messages:write` a `messages:read`, webhook
endpoint udalosti `message.*`. Projekt musí mať v Accounte schránku (Inbox → Schránky).

Existujúca notifikácia sa mení len v `via()` a pribudne `accountRef()`:

```php
class CanalContactNotice extends Notification implements ShouldQueue
{
    public function via($notifiable): array
    {
        return [AccountMailChannel::class];
    }

    public function toMail($notifiable): MailMessage   // ostáva, ako bola
    {
        return (new MailMessage)->subject('Overenie kontaktu')->view('mail.contact', [...]);
    }

    public function accountRef($notifiable): array
    {
        return [
            'type' => 'canal',
            'id' => $this->canal->id,
            'label' => 'Kanál: '.$this->canal->name,            // toto uvidíš v Inboxe
            'url' => config('app.admin_url').'/canals/'.$this->canal->id,
        ];
    }

    public function accountKind(): string { return 'canal.contact_verify'; }   // voliteľné
}

Notification::route('mail', $email)->notify(new CanalContactNotice($canal));
```

Notifikácia má byť `ShouldQueue`. Keď Account nebeží, fronta to skúsi znova. Blokovanú
adresu (`suppressed`) kanál len zaloguje a neopakuje.

Odpovede spracuje listener:

```php
// AppServiceProvider::boot()
Event::listen(function (AccountMessageEvent $e) {
    if ($e->refType() !== 'canal' || ! $canal = Canal::find($e->refId())) {
        return;                       // nie je naše / vec medzičasom zmizla
    }

    match ($e->event) {
        'message.received' => app(CanalEmails::class)->recordReply($canal, $e->email()),
        'message.bounced'  => app(CanalEmails::class)->recordBounce($canal, $e->email(), $e->message['bounce_type'] ?? 'hard'),
        default => null,
    };
});
```

Vlákno k veci si projekt môže zobraziť aj u seba: `AccountClient::conversations('canal', $id)`
a `AccountClient::conversation($uuid)`. Odhlásenie z e-mailov nahlás cez
`AccountClient::suppress($email)`.

**Čo cez Account neposielať:** reset hesla a overenie registrácie. Tie majú fungovať aj
vtedy, keď Account nebeží. Hromadné ponuky idú cez emailing nižšie.

## Emailing (ponuky a automatizácie)

Kampane, šablóny, kontakty, odhlásenie a meranie sú v Accounte (menu Emailing). Projekt
len hlási, čo sa stalo. Token potrebuje `mailing:write` (a `mailing:read` na stav kontaktu),
webhook endpoint podľa potreby `mailing.*`.

1. **Zaregistruj udalosti**, napr. v deploy príkaze. Bez toho ich operátor nevie vybrať:

```php
app(AccountClient::class)->registerMailingTriggers([
    ['key' => 'order_created', 'label' => 'Po vytvorení objednávky', 'short_label' => 'Po objednávke',
     'variables' => ['cislo_objednavky', 'firma', 'odkaz_objednavky'],
     // `kind` systémového mailu z /api/v1/messages – odpoveď vo vlákne ho zacituje
     'quote_kind' => 'order.OrderCreated'],
]);
```

2. **Hlás udalosti** cez frontu (`Jobs/ReportMailingEvent.php`). Premenné sa dosadia do textu
   kampane ako `{cislo_objednavky}`. Opakovanie s rovnakým `dedupeKey` nič nezdvojí:

```php
ReportMailingEvent::dispatch('order_created', $order->email,
    ['type' => 'order', 'id' => $order->id, 'label' => 'Objednávka '.$order->number, 'url' => $adminUrl],
    ['cislo_objednavky' => $order->number], "order:{$order->id}", $order->created_at)->afterCommit();
```

3. **Pri storne** zruš čakajúce maily: `AccountClient::cancelMailing('order', $order->id, except: ['order_cancelled'])`.

4. **Kontakty so súhlasom** (newsletter na webe, import zákazníkov):
   `AccountClient::upsertMailingContacts([['email' => …, 'permission_note' => 'registrácia na webe']])`.
   Ponuku dostane len kontakt so súhlasom. Servisná *odpoveď vo vlákne* ide každému adresátovi veci.

Odhlásenie rieši Account (odkaz v päte a `List-Unsubscribe`) a dá vedieť webhookom
`mailing.unsubscribed`. Platí len pre ponuky. Potvrdenia objednávok cez `/api/v1/messages`
chodia ďalej. Odoslaný mail k veci príde ako `mailing.delivery.sent` s `ref`, `subject` a `html`,
takže si ho projekt môže zapísať do histórie.

## Jazyk hlášok

`AccountClient` posiela `Accept-Language: {aktuálny jazyk projektu}`, takže
validačné chyby prídu v jazyku, ktorý má zákazník nastavený. Podporované je
`sk`, `cs`, `de` a `en`; pri inom jazyku sa použije slovenčina.

## Databáza v projekte

Projekt drží iba väzbu, nie kópiu firemných údajov:

```php
Schema::table('users', function (Blueprint $table) {
    $table->uuid('organization_id')->nullable()->index();
});
```

IČO, DIČ ani adresu do projektu **neukladaj**. Číta sa cez `AccountClient::organization()`
a drží v cache, ktorú invaliduje webhook.

Výnimka: ak potrebuješ podľa firmy triediť alebo filtrovať v SQL, sprav si
tenkú read-model tabuľku (`id`, `name`, `ico`, `synced_at`) a napĺňaj ju
z webhooku. Nič viac tam nedávaj.
