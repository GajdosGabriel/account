<?php

namespace App\Console\Commands;

use App\Models\MailingCampaign;
use App\Models\MailingContact;
use App\Models\MailingDelivery;
use App\Models\MailingLayout;
use App\Models\MailingLink;
use App\Models\MailingTemplate;
use App\Models\MailSuppression;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Prevzatie emailingu z projektu, ktorý ho mal doteraz sám (zastavy).
 *
 * Vstup je JSON z `php artisan emailing:export-account` v projekte. Tokeny
 * odhlásenia, kliknutí a otvorení sa zachovajú, aby fungovali odkazy
 * v už odoslaných mailoch (projekt ich presmeruje na Account).
 *
 * Import je jednorazový: druhý beh nad tým istým projektom odmietne, ak
 * už má kampane – inak by vznikli duplicitné kampane a štatistiky.
 */
class ImportMailing extends Command
{
    protected $signature = 'mailing:import {product : kľúč projektu} {file : JSON z exportu} {--dry-run : len vypíše, čo by sa prenieslo}';

    protected $description = 'Prevezme kontakty, šablóny, kampane a doručenia z exportu emailingu projektu.';

    public function handle(): int
    {
        $product = Product::where('key', $this->argument('product'))->first();

        if (! $product) {
            $this->error('Projekt neexistuje.');

            return self::FAILURE;
        }

        $data = json_decode((string) @file_get_contents($this->argument('file')), true);

        if (! is_array($data)) {
            $this->error('Súbor sa nedá prečítať ako JSON.');

            return self::FAILURE;
        }

        if (MailingCampaign::where('product_id', $product->id)->exists()) {
            $this->error('Projekt už má kampane v Accounte – import sa nespúšťa druhýkrát.');

            return self::FAILURE;
        }

        $this->table(['Čo', 'Počet'], collect(['layouts', 'templates', 'contacts', 'campaigns', 'deliveries', 'links'])
            ->map(fn ($key) => [$key, count($data[$key] ?? [])])->all());

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        DB::transaction(fn () => $this->import($product, $data));

        $this->info('Hotovo.');

        return self::SUCCESS;
    }

    /** @param array<string, array<int, array<string, mixed>>> $data */
    private function import(Product $product, array $data): void
    {
        $layouts = [];
        foreach ($data['layouts'] ?? [] as $row) {
            $layouts[$row['key']] = MailingLayout::create(['product_id' => $product->id, 'name' => $row['name'], 'html' => $row['html']])->id;
        }

        foreach ($data['templates'] ?? [] as $row) {
            MailingTemplate::create([
                'product_id' => $product->id,
                'layout_id' => $layouts[$row['layout'] ?? 'default'] ?? null,
                ...$this->only($row, ['name', 'subject', 'heading', 'body', 'button_label', 'button_url']),
            ]);
        }

        $contacts = [];
        foreach ($data['contacts'] ?? [] as $row) {
            $email = strtolower(trim($row['email']));
            $hard = ($row['bounce_type'] ?? null) === 'hard';

            $contact = MailingContact::create([
                'product_id' => $product->id,
                'email' => $email,
                'name' => $row['name'] ?? null,
                'permission_note' => $row['permission_note'] ?? null,
                'permission_at' => $this->date($row['permission_at'] ?? null),
                'token' => $row['token'],
                'unsubscribed_at' => $this->date($row['unsubscribed_at'] ?? null),
                // Trvalý bounce patrí do blokovaných adries, dočasný ostáva pri kontakte.
                'bounced_at' => $hard ? null : $this->date($row['bounced_at'] ?? null),
                'bounce_reason' => $row['bounce_reason'] ?? null,
                'soft_bounces' => (int) ($row['soft_bounces'] ?? 0),
            ]);
            $contact->forceFill(['created_at' => $this->date($row['created_at'] ?? null) ?? now()])->save();
            $contacts[$email] = $contact->id;

            if ($hard && ! empty($row['bounced_at'])) {
                MailSuppression::firstOrCreate(
                    ['product_id' => $product->id, 'email' => $email],
                    ['reason' => MailSuppression::HARD_BOUNCE, 'bounce_count' => 1, 'note' => $row['bounce_reason'] ?? null],
                );
            }
        }

        $campaigns = [];
        foreach ($data['campaigns'] ?? [] as $row) {
            $campaign = MailingCampaign::create([
                'product_id' => $product->id,
                'layout_id' => $layouts[$row['layout'] ?? 'default'] ?? null,
                ...$this->only($row, ['name', 'subject', 'heading', 'button_label', 'button_url', 'coupon_code', 'status', 'trigger_event']),
                'body' => $row['body'] ?? '',
                'coupon_valid_to' => $row['coupon_valid_to'] ?? null,
                'scheduled_at' => $this->date($row['scheduled_at'] ?? null),
                'delay_hours' => (int) ($row['delay_hours'] ?? 24),
                'track_clicks' => (bool) ($row['track_clicks'] ?? false),
                'track_opens' => (bool) ($row['track_opens'] ?? false),
                'reply_in_thread' => (bool) ($row['reply_to_order'] ?? false),
                'quote_event' => (bool) ($row['quote_event'] ?? true),
            ]);
            $campaign->forceFill(['created_at' => $this->date($row['created_at'] ?? null) ?? now()])->save();
            $campaigns[$row['id']] = $campaign->id;
        }

        $deliveries = [];
        foreach ($data['deliveries'] ?? [] as $row) {
            if (! isset($campaigns[$row['campaign_id']])) {
                continue;
            }

            $email = strtolower(trim($row['email']));
            $delivery = new MailingDelivery([
                'campaign_id' => $campaigns[$row['campaign_id']],
                'contact_id' => ($row['reply'] ?? false) ? null : ($contacts[$email] ?? null),
                'email' => $email,
                'status' => $row['status'],
                'ref_type' => $row['ref_type'] ?? null,
                'ref_id' => isset($row['ref_id']) ? (string) $row['ref_id'] : null,
                'dedupe_key' => (string) ($row['dedupe_key'] ?? ''),
                'payload' => array_filter([
                    'variables' => $row['variables'] ?? [],
                    'thread_id' => $row['thread_id'] ?? null,
                    'ref_label' => $row['ref_label'] ?? null,
                    'ref_url' => $row['ref_url'] ?? null,
                ]),
                'event_at' => $this->date($row['event_at'] ?? null),
                'due_at' => $this->date($row['due_at'] ?? null),
                'attempted_at' => $this->date($row['attempted_at'] ?? null),
                'sent_at' => $this->date($row['sent_at'] ?? null),
                'error' => isset($row['error']) ? mb_substr((string) $row['error'], 0, 255) : null,
                'open_token' => $row['open_token'] ?? null,
                'opened_at' => $this->date($row['opened_at'] ?? null),
                'clicked_at' => $this->date($row['clicked_at'] ?? null),
                'open_count' => (int) ($row['open_count'] ?? 0),
            ]);
            $delivery->created_at = $this->date($row['created_at'] ?? null) ?? now();
            $delivery->save();
            $deliveries[$row['id']] = $delivery->id;
        }

        foreach ($data['links'] ?? [] as $row) {
            if (isset($deliveries[$row['delivery_id']])) {
                MailingLink::create([
                    'delivery_id' => $deliveries[$row['delivery_id']],
                    ...$this->only($row, ['position', 'token', 'url', 'label', 'click_count']),
                    'clicked_at' => $this->date($row['clicked_at'] ?? null),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function only(array $row, array $keys): array
    {
        return array_intersect_key($row, array_flip($keys));
    }

    private function date(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }
}
