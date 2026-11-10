<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Štítky zadané ručne v Accounte. Sú oddelené od `tags`, ktoré posiela
 * projekt a pri každej synchronizácii ich celé nahradí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailing_contacts', function (Blueprint $table) {
            $table->json('manual_tags')->nullable()->after('tags');
        });
    }

    public function down(): void
    {
        Schema::table('mailing_contacts', function (Blueprint $table) {
            $table->dropColumn('manual_tags');
        });
    }
};
