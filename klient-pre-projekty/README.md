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
