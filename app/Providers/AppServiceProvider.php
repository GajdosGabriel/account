<?php

namespace App\Providers;

use App\Events\SubscriptionStatusChanged;
use App\Listeners\BroadcastSubscriptionChange;
use App\Models\Organization;
use App\Observers\OrganizationObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
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

        Organization::observe(OrganizationObserver::class);

        Event::listen(SubscriptionStatusChanged::class, BroadcastSubscriptionChange::class);
    }
}
