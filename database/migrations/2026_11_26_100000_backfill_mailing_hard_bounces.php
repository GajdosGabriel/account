<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Doplnenie po oprave spracovania správ o nedoručení: trvalý bounce kampane,
 * ktorého správa neuviedla adresáta, adresu nezablokoval, a kontakt
 * s blokovanou adresou nemal uložený dôvod.
 *
 * Adresa, ktorej kontakt sa po nedoručení menil (napr. ho operátor znova
 * aktivoval), sa neblokuje.
 */
return new class extends Migration
{
    public function up(): void
    {
        $deliveries = DB::table('mailing_deliveries as d')
            ->join('mailing_campaigns as c', 'c.id', '=', 'd.campaign_id')
            ->where('d.status', 'bounced')
            ->where('d.error', 'like', '5%')
            ->whereNotExists(fn ($q) => $q->from('mail_suppressions as s')
                ->whereColumn('s.product_id', 'c.product_id')
                ->whereColumn('s.email', 'd.email'))
            ->whereNotExists(fn ($q) => $q->from('mailing_contacts as mc')
                ->whereColumn('mc.product_id', 'c.product_id')
                ->whereColumn('mc.email', 'd.email')
                ->whereNull('mc.deleted_at')
                ->whereColumn('mc.updated_at', '>', 'd.updated_at'))
            ->orderBy('d.id')
            ->get(['c.product_id', 'd.email', 'd.error', 'd.updated_at']);

        foreach ($deliveries->unique(fn ($d) => $d->product_id.':'.strtolower($d->email)) as $d) {
            DB::table('mail_suppressions')->insertOrIgnore([
                'product_id' => $d->product_id,
                'email' => strtolower(trim($d->email)),
                'reason' => 'hard_bounce',
                'bounce_count' => 1,
                'note' => $d->error,
                'created_at' => $d->updated_at,
                'updated_at' => now(),
            ]);
        }

        DB::table('mail_suppressions')
            ->where('reason', 'hard_bounce')
            ->whereNotNull('note')
            ->orderBy('id')
            ->each(fn ($s) => DB::table('mailing_contacts')
                ->where('product_id', $s->product_id)
                ->where('email', $s->email)
                ->whereNull('bounce_reason')
                ->update(['bounce_reason' => mb_substr($s->note, 0, 255)]));
    }

    public function down(): void
    {
        // Dátová oprava – späť sa nevracia.
    }
};
