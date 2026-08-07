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

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        Organization::observe(OrganizationObserver::class);

        Event::listen(SubscriptionStatusChanged::class, BroadcastSubscriptionChange::class);
    }
}
