<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Číselné rady dokladov.
 *
 * Slovenský zákon o účtovníctve vyžaduje neprerušený vzostupný číselný rad
 * v rámci účtovného obdobia. Preto sa poradové číslo drží v databáze
 * a inkrementuje sa atomicky (SELECT ... FOR UPDATE), nikdy nie cez MAX(number).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_number_series', function (Blueprint $table) {
            $table->id();

            $table->string('key', 40)->unique();          // napr. "faktura", "zaloha", "dobropis"
            $table->string('name');
            $table->string('document_type', 20);          // invoice|proforma|credit_note

            // Vzor čísla. Zástupné znaky: {YYYY} {YY} {MM} {SEQ}
            $table->string('pattern', 40)->default('{YYYY}{SEQ}');
            $table->unsignedTinyInteger('sequence_length')->default(4);

            // Rad sa reštartuje na začiatku roka (resp. mesiaca).
            $table->string('reset_period', 10)->default('year'); // year|month|never

            $table->unsignedSmallInteger('period_year')->nullable();
            $table->unsignedTinyInteger('period_month')->nullable();
            $table->unsignedInteger('next_sequence')->default(1);

            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['document_type', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_number_series');
    }
};
