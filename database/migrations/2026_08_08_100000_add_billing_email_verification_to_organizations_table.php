<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overenie fakturačného e-mailu.
 *
 * Faktúra nesie IČO, adresu aj sumy – ak sa v adrese pomýli jedno písmeno,
 * putuje to cudziemu človeku a nikto sa o tom nedozvie. Dovtedy sa e-mail
 * bral ako platný len preto, že prešiel `email` validáciou.
 *
 * Ukladá sa aj overená adresa, nielen čas. Vďaka tomu netreba nikde riešiť
 * „pri zmene zruš overenie“ – porovnanie s aktuálnou adresou to spraví samo
 * a funguje aj vtedy, keď stĺpec zmení iná cesta (import, tinker, iný projekt).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('billing_email_verified_address')->nullable()->after('billing_email');
            $table->timestamp('billing_email_verified_at')->nullable()->after('billing_email_verified_address');
            // Proti opakovanému odosielaniu pri každom uložení formulára.
            $table->timestamp('billing_email_verification_sent_at')->nullable()->after('billing_email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'billing_email_verified_address',
                'billing_email_verified_at',
                'billing_email_verification_sent_at',
            ]);
        });
    }
};
