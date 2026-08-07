<?php

use App\Console\Commands\GenerateSubscriptionInvoices;
use App\Console\Commands\ProcessSubscriptionLifecycle;
use App\Console\Commands\RetryWebhookDeliveries;
use App\Console\Commands\SendInvoiceReminders;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ProcessSubscriptionLifecycle::class)->dailyAt('03:00');
Schedule::command(RetryWebhookDeliveries::class)->everyFiveMinutes();

/*
|--------------------------------------------------------------------------
| Fakturácia
|--------------------------------------------------------------------------
|
| Faktúry vznikajú ráno pred otvorením účtárne, upomienky odchádzajú
| dopoludnia – nie o polnoci, aby e-mail neskončil ako prvý neprečítaný.
|
*/

Schedule::command(GenerateSubscriptionInvoices::class)->dailyAt('06:00');
Schedule::command(SendInvoiceReminders::class)->weekdays()->dailyAt('09:30');
