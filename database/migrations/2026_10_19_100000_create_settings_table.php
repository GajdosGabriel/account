<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nastavenia meniteľné z back-office.
 *
 * Kľúč je cesta v configu (napr. `invoicing.reminders.enabled`), hodnota
 * JSON. Pri štarte aplikácie sa uložené hodnoty prekryjú cez config –
 * čo tu nie je, ostáva tak, ako to určuje .env.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
