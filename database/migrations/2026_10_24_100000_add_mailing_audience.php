<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adresár kontaktov plní projekt (štítky, atribúty, súhlas) a kampaň si
 * z neho vyberá adresátov podľa štítkov a druhu (ponuka / oznámenie o službe).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Štítky, ktoré projekt posiela, s popisom pre výber segmentu.
        Schema::create('mailing_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->string('label');
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'key']);
        });

        Schema::table('mailing_contacts', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('attributes');
            // Kedy sa naposledy zmenilo odhlásenie – novšia zmena z projektu ho prepíše.
            $table->timestamp('unsubscribed_changed_at')->nullable()->after('unsubscribed_at');
        });

        Schema::table('mailing_campaigns', function (Blueprint $table) {
            $table->string('kind', 12)->default('offer')->after('name');   // offer | service
            $table->json('audience')->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('mailing_campaigns', function (Blueprint $table) {
            $table->dropColumn(['kind', 'audience']);
        });

        Schema::table('mailing_contacts', function (Blueprint $table) {
            $table->dropColumn(['tags', 'unsubscribed_changed_at']);
        });

        Schema::dropIfExists('mailing_tags');
    }
};
