<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Koreň e-mailového vlákna, ktorý určil projekt.
 *
 * Projekt, ktorý posielal maily sám (napr. zastavy s Message-ID
 * `order-{uuid}@…`), ho pošle pri prvej správe cez Account. Ďalšie maily
 * naň odkazujú v References – zákazníkovi ostane jedno vlákno – a odpoveď
 * na starý mail sa podľa neho spáruje so správnou konverzáciou.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('thread_ref')->nullable()->after('ref_url')->index();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['thread_ref']);
            $table->dropColumn('thread_ref');
        });
    }
};
