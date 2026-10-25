<?php

use App\Console\Commands\FetchMailboxes;
use App\Console\Commands\GenerateSubscriptionInvoices;
use App\Console\Commands\RetryConversationMessages;
use App\Console\Commands\ProcessSubscriptionLifecycle;
use App\Console\Commands\PurgeSpamConversations;
use App\Console\Commands\RetryWebhookDeliveries;
use App\Console\Commands\SendInvoiceReminders;
use App\Console\Commands\SendMailingBatch;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ProcessSubscriptionLifecycle::class)->dailyAt('03:00');
Schedule::command(RetryWebhookDeliveries::class)->everyFiveMinutes();

/*
|--------------------------------------------------------------------------
| Komunikácia s klientmi projektov
|--------------------------------------------------------------------------
|
| Odpovede klientov sa vyzdvihujú zo schránok každé dve minúty – dosť
| rýchlo na rozhovor, dosť zriedka, aby si poskytovateľ nevšímal pripojenia.
|
*/

Schedule::command(FetchMailboxes::class)->everyTwoMinutes()->withoutOverlapping(10);
Schedule::command(RetryConversationMessages::class)->everyFiveMinutes();
Schedule::command(PurgeSpamConversations::class)->dailyAt('03:30');

// Kampane po malých dávkach – hodinový limit projektu stráži MailingSender.
Schedule::command(SendMailingBatch::class)->everyMinute()->withoutOverlapping(10);

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
