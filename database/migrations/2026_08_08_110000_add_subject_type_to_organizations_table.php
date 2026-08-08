<?php

use App\Enums\SubjectType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Firma alebo súkromná osoba.
 *
 * Doteraz sa mlčky predpokladalo, že každý zákazník má IČO. Občan sa tak
 * nedal uložiť bez toho, aby mu `missingBillingFields()` navždy hlásilo
 * chýbajúce IČO, ktoré nikdy mať nebude.
 *
 * Nový stĺpec je zámerne samostatný a nie odvodený z `legal_form`:
 * právna forma je nepovinná a používa sa na popis, kým podľa tohto sa
 * rozhoduje, čo sa vôbec smie vyžadovať.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('subject_type', 10)
                ->default(SubjectType::Company->value)
                ->after('legal_form');
        });

        // Firmy bez IČO, ktoré vznikli pred týmto rozlíšením, necháme ako sú –
        // operátor ich prepne ručne. Automatické hádanie „nemá IČO, teda je to
        // občan“ by z rozrobených firemných záznamov spravilo osoby.
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('subject_type');
        });
    }
};
