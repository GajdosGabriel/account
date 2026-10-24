<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kontakt, ktorý projekt odstráni, sa len skryje. Keď ho projekt pošle znova
 * (nová objednávka), obnoví sa aj s odhlásením, tokenom a históriou.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailing_contacts', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('mailing_contacts', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
