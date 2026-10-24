<?php

namespace App\Services\Mailing;

use App\Models\MailingCampaign;
use App\Models\MailingContact;
use App\Models\MailingDelivery;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Udalosti z projektov, ktoré spúšťajú automatizácie.
 *
 * Projekt povie „pri objednávke 12 nastala udalosť order_created pre
 * adresu X“ a pribalí premenné do textu. Account podľa aktívnych kampaní
 * zaradí doručenia s oneskorením od času udalosti.
 */
class MailingEvents
{
    /**
     * Opakované hlásenie tej istej udalosti (rovnaký `dedupe_key`) je bezpečné.
     *
     * @param  array{type: string, id: string|int, label?: ?string, url?: ?string}|null  $ref
     * @param  array<string, mixed>  $variables
     * @return int počet nových doručení
     */
    public function record(
        Product $product,
        string $event,
        string $email,
        ?string $name = null,
        ?array $ref = null,
        string $dedupeKey = '',
        ?Carbon $at = null,
        array $variables = [],
        ?string $threadId = null,
    ): int {
        $email = strtolower(trim($email));
        $at ??= now();

        if ($email === '') {
            return 0;
        }

        // Či kontakt smie dostať kampaň (súhlas, segment), rozhodne každá kampaň sama.
        $contact = MailingContact::query()
            ->where('product_id', $product->id)
            ->where('email', $email)
            ->notBlocked()
            ->first();

        return DB::transaction(function () use ($product, $event, $email, $name, $ref, $dedupeKey, $at, $variables, $threadId, $contact) {
            // Zámok kampaní radí súbežné udalosti za sebou – deduplikácia potom drží.
            $campaigns = MailingCampaign::query()
                ->where('product_id', $product->id)
                ->where('trigger_event', $event)
                ->where('status', MailingCampaign::QUEUED)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $created = 0;

            foreach ($campaigns as $campaign) {
                // Servisná odpoveď ide každému adresátovi veci, aj mimo adresára ponúk.
                // Ponuka len kontaktu so súhlasom, oznámenie každému v segmente.
                if (! $campaign->isReply() && ! ($contact && $contact->isReachable($campaign))) {
                    continue;
                }

                $created += DB::table('mailing_deliveries')->insertOrIgnore([
                    'campaign_id' => $campaign->id,
                    'contact_id' => $campaign->isReply() ? null : $contact->id,
                    'email' => $email,
                    'status' => MailingDelivery::PENDING,
                    'ref_type' => $ref['type'] ?? null,
                    'ref_id' => isset($ref['id']) ? (string) $ref['id'] : null,
                    'dedupe_key' => $dedupeKey,
                    'payload' => json_encode(array_filter([
                        'name' => $name,
                        'variables' => $variables,
                        'thread_id' => $threadId,
                        'ref_label' => $ref['label'] ?? null,
                        'ref_url' => $ref['url'] ?? null,
                    ], fn ($v) => $v !== null && $v !== []), JSON_UNESCAPED_UNICODE),
                    'event_at' => $at,
                    'due_at' => $at->copy()->addHours($campaign->delay_hours),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $created;
        });
    }

    /**
     * Vec v projekte sa zrušila (storno objednávky): čakajúce doručenia k nej
     * sa preskočia – okrem kampaní na udalosti v `$except` (napr. práve storno).
     *
     * @param  array<int, string>  $except
     * @return int počet preskočených
     */
    public function cancel(Product $product, string $refType, string $refId, array $except = []): int
    {
        return MailingDelivery::query()
            ->where('status', MailingDelivery::PENDING)
            ->where('ref_type', $refType)
            ->where('ref_id', $refId)
            ->whereHas('campaign', fn ($q) => $q->where('product_id', $product->id)
                ->when($except !== [], fn ($q) => $q->whereNotIn('trigger_event', $except)))
            ->update(['status' => MailingDelivery::SKIPPED, 'error' => __('mailing.errors.cancelled_by_project'), 'updated_at' => now()]);
    }
}
