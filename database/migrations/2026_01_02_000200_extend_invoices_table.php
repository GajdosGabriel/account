<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doplnenie faktúr o všetko, čo slovenská faktúra a moderný fakturačný
 * systém potrebuje: typy dokladov, symboly platby, dátum dodania (DUZP),
 * snapshot dodávateľa, evidencia odoslania a čiastočných úhrad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // --- typ a väzby -------------------------------------------------
            $table->string('type', 20)->default('invoice')->after('number');   // invoice|proforma|credit_note
            $table->foreignId('number_series_id')->nullable()->after('type')
                ->constrained('invoice_number_series')->nullOnDelete();
            $table->unsignedInteger('sequence')->nullable()->after('number_series_id');

            // Dobropis / faktúra vystavená k zálohovej faktúre.
            $table->foreignId('parent_invoice_id')->nullable()->after('sequence')
                ->constrained('invoices')->nullOnDelete();

            // --- symboly platby (SK/CZ zvyklosť) -----------------------------
            $table->string('variable_symbol', 10)->nullable()->after('currency');
            $table->string('constant_symbol', 4)->nullable()->after('variable_symbol');
            $table->string('specific_symbol', 10)->nullable()->after('constant_symbol');
            $table->string('payment_method', 20)->default('transfer')->after('specific_symbol');

            // --- dátumy ------------------------------------------------------
            // Dátum dodania služby = deň vzniku daňovej povinnosti.
            $table->date('delivered_at')->nullable()->after('issued_at');

            // --- sumy --------------------------------------------------------
            $table->integer('discount_cents')->default(0)->after('subtotal_cents');
            $table->integer('rounding_cents')->default(0)->after('total_cents');
            $table->unsignedInteger('paid_cents')->default(0)->after('rounding_cents');

            // Rozpad DPH podľa sadzieb – rekapitulácia na faktúre.
            $table->json('vat_summary')->nullable()->after('paid_cents');

            // --- texty a snapshoty -------------------------------------------
            $table->json('supplier_snapshot')->nullable()->after('billing_snapshot');
            $table->string('locale', 5)->default('sk')->after('supplier_snapshot');
            $table->text('note')->nullable()->after('locale');           // vidí zákazník
            $table->text('internal_note')->nullable()->after('note');    // interné
            $table->string('vat_note')->nullable()->after('internal_note'); // "Prenesenie daňovej povinnosti…"

            // --- odoslanie a upomienky ---------------------------------------
            $table->timestamp('sent_at')->nullable()->after('paid_at');
            $table->unsignedTinyInteger('sent_count')->default(0)->after('sent_at');
            $table->string('sent_to')->nullable()->after('sent_count');
            $table->timestamp('last_reminder_at')->nullable()->after('sent_to');
            $table->unsignedTinyInteger('reminder_count')->default(0)->after('last_reminder_at');
            $table->timestamp('cancelled_at')->nullable()->after('reminder_count');

            // --- ostatné -----------------------------------------------------
            $table->foreignId('created_by')->nullable()->after('cancelled_at')
                ->constrained('users')->nullOnDelete();
            $table->string('pdf_path')->nullable()->after('pdf_url');
            $table->timestamp('exported_at')->nullable()->after('pdf_path');

            $table->index(['type', 'status']);
            $table->index('due_at');
            $table->index('variable_symbol');
        });

        // subtotal/vat/total môžu byť pri dobropise záporné.
        Schema::table('invoices', function (Blueprint $table) {
            $table->integer('subtotal_cents')->default(0)->change();
            $table->integer('vat_cents')->default(0)->change();
            $table->integer('total_cents')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['number_series_id']);
            $table->dropForeign(['parent_invoice_id']);
            $table->dropForeign(['created_by']);

            $table->dropIndex(['type', 'status']);
            $table->dropIndex(['due_at']);
            $table->dropIndex(['variable_symbol']);

            $table->dropColumn([
                'type', 'number_series_id', 'sequence', 'parent_invoice_id',
                'variable_symbol', 'constant_symbol', 'specific_symbol', 'payment_method',
                'delivered_at', 'discount_cents', 'rounding_cents', 'paid_cents', 'vat_summary',
                'supplier_snapshot', 'locale', 'note', 'internal_note', 'vat_note',
                'sent_at', 'sent_count', 'sent_to', 'last_reminder_at', 'reminder_count',
                'cancelled_at', 'created_by', 'pdf_path', 'exported_at',
            ]);
        });
    }
};
