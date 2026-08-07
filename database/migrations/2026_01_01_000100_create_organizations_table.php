<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            // verejny identifikator - projekty ho drzia na svojich riadkoch
            $table->uuid('uuid')->unique();

            /* ---------------- Identifikácia ---------------- */

            // bezny nazov, pod ktorym firmu poznas
            $table->string('name');
            // presne obchodne meno podla registra - patri na fakturu
            $table->string('legal_name')->nullable();
            // sro | zivnost | as | ks | vos | druzstvo | nezisk | fyzicka | ine
            $table->string('legal_form', 20)->nullable();

            // ICO je prirodzeny kluc na deduplikaciu naprieč projektmi
            $table->string('ico', 12)->nullable()->unique();
            $table->string('dic', 15)->nullable();
            $table->string('ic_dph', 15)->nullable();
            // non_payer | payer | reg_7 | reg_7a
            $table->string('vat_mode', 12)->default('non_payer');
            // One Stop Shop - eshopy predavajuce spotrebitelom do EU
            $table->boolean('oss_registered')->default(false);

            /* ---------------- Zápis v registri ---------------- */
            // Povinny udaj na fakture aj v paticke obchodnej korespondencie:
            // "OS Bratislava I, odd. Sro, vl. c. 12345/B" alebo cislo zivnosti.

            $table->string('register_court')->nullable();
            $table->string('register_section', 20)->nullable();
            $table->string('register_insert', 30)->nullable();
            $table->date('established_at')->nullable();

            /* ---------------- Sídlo / miesto podnikania ----------------
             | Adresa ako na živnostenskom liste alebo vo výpise z OR.
             | Je vždy práve jedna, preto zostáva priamo tu – ostatné
             | adresy sú v `organization_addresses`.
             */

            $table->string('street')->nullable();
            // cislo zvlast, fakturacne systemy a Packeta ho chcu oddelene
            $table->string('street_no', 30)->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 12)->nullable();
            $table->string('region', 80)->nullable();
            $table->char('country', 2)->default('SK');

            /* ---------------- Kontakt ---------------- */

            $table->string('email')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('website')->nullable();

            /* ---------------- Banka ---------------- */

            $table->string('bank_name', 120)->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('swift', 11)->nullable();

            /* ---------------- Fakturačné preferencie ---------------- */

            $table->char('currency', 3)->default('EUR');
            $table->unsignedSmallInteger('payment_terms_days')->default(14);
            // transfer | card | cash | cod
            $table->string('payment_method', 12)->default('transfer');
            $table->char('invoice_language', 2)->default('sk');
            // email | post | both
            $table->string('invoice_delivery', 10)->default('email');
            // volitelny konstantny symbol / interne cislo dodavatela u zakaznika
            $table->string('supplier_number', 40)->nullable();

            /* ---------------- Overenie v registroch ---------------- */

            $table->timestamp('ico_verified_at')->nullable();
            $table->timestamp('vat_verified_at')->nullable();
            $table->json('registry_snapshot')->nullable();

            /* ---------------- Interné ---------------- */

            $table->string('status', 20)->default('active'); // active | suspended | archived
            $table->text('note')->nullable();
            $table->json('settings')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'country']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
