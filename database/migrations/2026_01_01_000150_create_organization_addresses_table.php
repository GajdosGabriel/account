<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ďalšie adresy firmy okrem sídla.
     *
     *  mailing  – adresa na zasielanie pošty (keď sa líši od sídla)
     *  delivery – dodacia adresa; eshop ich potrebuje viac (sklady, predajne)
     *  branch   – prevádzkareň
     *
     * Sídlo tu NIE JE – to je priamo na `organizations`, lebo je vždy jedno
     * a používa sa na fakturáciu aj filtrovanie.
     */
    public function up(): void
    {
        Schema::create('organization_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('type', 20);              // mailing | delivery | branch
            $table->string('label', 120)->nullable(); // "Sklad Bratislava"

            // meno prijemcu alebo oddelenie – na obalke byva ine ako nazov firmy
            $table->string('recipient')->nullable();

            $table->string('street');
            $table->string('street_no', 30)->nullable();
            $table->string('city');
            $table->string('postal_code', 12);
            $table->string('region', 80)->nullable();
            $table->char('country', 2)->default('SK');

            $table->string('phone', 40)->nullable();
            $table->text('note')->nullable();        // "zvonček vzadu, po 15:00"

            // predvolena adresa pre dany typ
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->index(['organization_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_addresses');
    }
};
