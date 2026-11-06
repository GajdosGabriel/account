<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schránka, z ktorej sa doručenie skutočne odoslalo. Hodinový limit
 * emailingu sa počíta pre každú schránku zvlášť.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailing_deliveries', function (Blueprint $table) {
            $table->foreignId('mailbox_id')->nullable()->after('contact_id')->constrained()->nullOnDelete();
            $table->index(['mailbox_id', 'attempted_at']);
        });

        // Staršie pokusy dostanú schránku kampane (pri predvolenej ostane prázdna).
        DB::table('mailing_deliveries')->whereNotNull('attempted_at')->update([
            'mailbox_id' => DB::raw('(SELECT mailbox_id FROM mailing_campaigns WHERE mailing_campaigns.id = mailing_deliveries.campaign_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('mailing_deliveries', function (Blueprint $table) {
            $table->dropForeign(['mailbox_id']);
            $table->dropIndex(['mailbox_id', 'attempted_at']);
            $table->dropColumn('mailbox_id');
        });
    }
};
