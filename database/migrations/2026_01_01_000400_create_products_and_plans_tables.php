<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "product" = jeden z pripojenych projektov
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();   // projekt-1
            $table->string('name');
            $table->string('url')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        /*
         * Katalog funkcii daneho produktu.
         *
         * Bez neho je `plans.features` volny JSON, kde sa preklep v kluci
         * neda odhalit - projekt limit nenajde a pusti neobmedzene.
         * Katalog urcuje, ake kluce vobec existuju a akeho su typu.
         */
        Schema::create('product_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);                 // max_records | export
            $table->string('name');                    // "Počet záznamov"
            $table->string('type', 10);                // flag | limit
            $table->string('unit', 30)->nullable();    // "záznamov"
            // metrika, ktoru projekt hlasi do /usage; parovana s limitom
            $table->string('metric', 60)->nullable();  // records
            $table->json('default_value')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'key']);
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('key', 50);             // free | standard | pro
            $table->string('name');
            $table->unsignedInteger('price_cents')->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->string('interval', 10)->default('month'); // month | year
            $table->unsignedSmallInteger('trial_days')->default(0);
            // { "max_records": 10, "export": true }  null hodnota = neobmedzene
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('external_price_id')->nullable(); // Stripe price id
            $table->timestamps();

            $table->unique(['product_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
        Schema::dropIfExists('product_features');
        Schema::dropIfExists('products');
    }
};
