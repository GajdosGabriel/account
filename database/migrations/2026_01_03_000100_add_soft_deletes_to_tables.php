<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doplnenie soft delete tam, kde má zmysel.
 *
 * Pravidlo: mäkko sa mažú ENTITY, ktoré niekto spravuje ručne a ktorých
 * zmazanie môže byť omyl – firmy, projekty, plány, doklady, kontakty.
 *
 * Zámerne sa NEMAŽÚ mäkko:
 *   audit_logs, subscription_events, invoice_events   – nemenná história;
 *                                                       mazateľný audit log
 *                                                       nie je audit log
 *   webhook_deliveries, usage_reports                 – technické záznamy,
 *                                                       čistia sa dávkovo
 *   organization_product                              – pivot, väzba buď je,
 *                                                       alebo nie je
 *   service_clients                                   – token sa zruší
 *                                                       (revoked_at), nie
 *                                                       zmaže; kôš tu nič
 *                                                       nerieši
 *   webhook_endpoints                                 – kus konfigurácie,
 *                                                       buď posielame, alebo
 *                                                       nie
 *
 * (service_clients a webhook_endpoints kôš krátko mali – odobrala ho
 *  migrácia 2026_08_30_000000.)
 *
 * Ak by tieto tabuľky dostali `deleted_at`, každý dotaz by musel riešiť
 * scope navyše a nezískali by sme tým nič.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $tables = [
        'users',
        'organization_addresses',
        'organization_contacts',
        'products',
        'product_features',
        'plans',
        'subscriptions',
        'entitlement_overrides',
        'invoices',
        'invoice_items',
        'invoice_number_series',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
