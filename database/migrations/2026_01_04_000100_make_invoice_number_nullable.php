<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Číslo dokladu smie chýbať, kým je doklad konceptom.
 *
 * Číslo prideľuje InvoiceNumberGenerator až pri vystavení – dovtedy
 * žiadne nie je a ani byť nesmie: číselný rad musí ísť bez dier, takže
 * nevystavený koncept doň nemôže zaberať miesto. Pôvodná schéma to mala
 * `NOT NULL`, čím sa koncept nedal vôbec uložiť:
 *
 *   SQLSTATE[HY000]: Field 'number' doesn't have a default value
 *
 * Unikátny index zostáva. MySQL aj SQLite v ňom pripúšťajú viac NULL
 * hodnôt, takže konceptov môže byť koľkokoľvek, ale dve vystavené
 * faktúry rovnaké číslo nedostanú.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('number')->nullable()->change();
        });

        // Ak niekde stihol vzniknúť riadok s prázdnym reťazcom namiesto
        // NULL, unikátny index by ďalší koncept odmietol.
        DB::table('invoices')->where('number', '')->update(['number' => null]);
    }

    /**
     * Späť sa dá len vtedy, keď každý doklad číslo má – inak by
     * databáza zmenu odmietla, a to je správne.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('number')->nullable(false)->change();
        });
    }
};
