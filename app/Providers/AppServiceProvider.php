<?php

namespace App\Providers;

use App\Events\SubscriptionStatusChanged;
use App\Listeners\BroadcastSubscriptionChange;
use App\Models\Organization;
use App\Models\Setting;
use App\Observers\OrganizationObserver;
use App\Services\Messaging\ImapMailboxReader;
use App\Services\Messaging\MailboxReader;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MailboxReader::class, ImapMailboxReader::class);
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        // Schéma odkazov sa riadi APP_URL, nie tým, či ide o produkciu.
        // Natvrdo vynútené https rozbilo nasadenie, kde je aplikácia dostupná
        // len po HTTP na neštandardnom porte — presmerovanie na prihlásenie
        // viedlo na https bez portu a skončilo na odmietnutom pripojení.
        if (parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https') {
            URL::forceScheme('https');
        }

        // Nastavenia z back-office (/invoices/settings) majú prednosť pred .env.
        Setting::applyToConfig();

        // Operátor vidí faktúry, tokeny projektov aj heslá schránok –
        // osem znakov z predvolieb Laravelu je na to málo.
        Password::defaults(fn () => Password::min(12)->letters()->numbers());

        // API pre projekty: limit na token, nie na IP – projekty bývajú na
        // spoločnom hostingu a jedna IP by brzdila všetky naraz.
        RateLimiter::for('api', function (Request $request) {
            $token = $request->bearerToken();

            return Limit::perMinute((int) config('accounts.api.rate_limit'))
                ->by($token ? 'token:'.hash('sha256', $token) : 'ip:'.$request->ip());
        });

        Organization::observe(OrganizationObserver::class);

        Event::listen(SubscriptionStatusChanged::class, BroadcastSubscriptionChange::class);
    }
}
