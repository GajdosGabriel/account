<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `paid_cents` bol jediný zo súm ako UNSIGNED, ostatné (subtotal, discount,
 * vat, total, rounding) sú signed – dobropis má zámerne zápornú sumu.
 *
 * MySQL/MariaDB pri odčítaní signed − unsigned povyšuje výsledok na unsigned,
 * takže `SUM(total_cents - paid_cents)` nad dobropisom skončil na
 * „BIGINT UNSIGNED value is out of range“ (chyba 1690). Zrovnanie typu
 * problém rieši pri zdroji, bez CAST-ov v každom dotaze.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->integer('paid_cents')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedInteger('paid_cents')->default(0)->change();
        });
    }
};
