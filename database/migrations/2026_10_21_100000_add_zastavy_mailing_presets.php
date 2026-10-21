<?php

use App\Console\Commands\ImportMailing;
use App\Models\MailingLayout;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;

/**
 * Prednastavené šablóny zastáv pre prípad, že import emailingu prebehol
 * skôr, než ich `mailing:import` začal preberať. Bez importovaných layoutov
 * sa nič nerobí – šablóny potom doplní samotný import.
 */
return new class extends Migration
{
    public function up(): void
    {
        $product = Product::where('key', 'zastavy')->first();

        if ($product && MailingLayout::where('product_id', $product->id)->exists()) {
            ImportMailing::addPresets($product);
        }
    }

    public function down(): void
    {
        // Šablóny mohli medzitým dostať úpravy alebo kampane – ostávajú.
    }
};
