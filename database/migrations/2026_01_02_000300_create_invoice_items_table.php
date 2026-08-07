<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Položky faktúry.
 *
 * Sumy sa držia v centoch ako celé čísla – s desatinnými číslami sa
 * pri DPH nikdy nepočíta. Jednotková cena má 4 desatinné miesta
 * (uložené ako stotiny centa), aby sedeli aj ceny typu 0,0125 € / kus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            // Odkiaľ položka vznikla (voliteľné – ručné položky ich nemajú).
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->string('description');
            $table->text('detail')->nullable();

            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit', 20)->default('ks');       // ks, mesiac, hod, MB…

            // Jednotková cena bez DPH v stotinách centa (1 € = 10000).
            $table->integer('unit_price')->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(0);

            $table->integer('subtotal_cents')->default(0);   // po zľave, bez DPH
            $table->integer('vat_cents')->default(0);
            $table->integer('total_cents')->default(0);

            // Zúčtované obdobie – "Predplatné 1. 2. – 28. 2. 2026"
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['invoice_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
