<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Odobratie soft delete zo service_clients a webhook_endpoints.
 *
 * Kôš tu nemal zmysel: token, ktorý má zostať v evidencii kvôli auditu,
 * sa zruší (revoked_at), nie zmaže. Webhook je len kus konfigurácie –
 * buď ho posielame, alebo nie. Zmazané záznamy sa preto najprv natvrdo
 * odstránia a stĺpec `deleted_at` odíde.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $tables = [
        'service_clients',
        'webhook_endpoints',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            // Záznamy v koši už nemajú kam – kôš zaniká.
            DB::table($table)->whereNotNull('deleted_at')->delete();

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropSoftDeletes();
            });
        }
    }

    public function down(): void
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
};
