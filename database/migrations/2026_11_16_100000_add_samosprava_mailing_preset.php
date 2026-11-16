<?php

use App\Console\Commands\ImportMailing;
use App\Models\MailingLayout;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Šablóna si pamätá odosielateľa (schránku), kampaň z nej ho prevezme.
 *
 * K tomu layout „Samospráva“, schránka samosprava@spisor.eu a šablóna
 * pozvania obcí na spoluprácu pri vývoji Samosprávy. Posiela sa z emailingu
 * zastáv (tam sú kontakty obcí), rovnako ako layout Spisor.eu. Bez
 * importovaného emailingu sa predvoľby nerobia – doplní ich `mailing:import`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailing_templates', function (Blueprint $table) {
            $table->foreignId('mailbox_id')->nullable()->after('layout_id')->constrained()->nullOnDelete();
        });

        $product = Product::where('key', 'zastavy')->first();

        if ($product && MailingLayout::where('product_id', $product->id)->exists()) {
            ImportMailing::addPresets($product);
        }
    }

    public function down(): void
    {
        // Layout, schránka a šablóna mohli medzitým dostať úpravy alebo kampane – ostávajú.
        Schema::table('mailing_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mailbox_id');
        });
    }
};
