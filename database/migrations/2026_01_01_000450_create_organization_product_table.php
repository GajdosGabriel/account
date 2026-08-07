<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ktoré firmy patria ktorému projektu.
     *
     * Dve úlohy naraz:
     *  1. izolácia – projekt 1 nevidí zákazníkov projektu 2,
     *  2. prehľad – hneď vidíš, ktorá firma používa viac tvojich aplikácií.
     */
    public function up(): void
    {
        Schema::create('organization_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // volitelny identifikator firmy na strane projektu
            $table->string('external_ref')->nullable();
            $table->timestamp('linked_at')->useCurrent();
            $table->timestamps();

            $table->unique(['organization_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_product');
    }
};
