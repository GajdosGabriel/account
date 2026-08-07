<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aktuálna spotreba, ktorú projekt hlási späť.
     *
     * Limit vynucuje projekt (on jediný vidí svoje dáta), ale Account
     * potrebuje vedieť, kto sa blíži k stropu – kvôli upozorneniam
     * a prípadnému účtovaniu nadspotreby.
     */
    public function up(): void
    {
        Schema::create('usage_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('metric', 60);          // records | users | storage_mb
            $table->unsignedBigInteger('value');
            $table->timestamp('reported_at');
            $table->timestamps();

            // drzime vzdy iba poslednu hodnotu pre kombinaciu
            $table->unique(['organization_id', 'product_id', 'metric'], 'usage_reports_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_reports');
    }
};
