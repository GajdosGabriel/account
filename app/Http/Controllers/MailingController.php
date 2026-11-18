<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SharesMailingData;
use App\Models\Mailbox;
use App\Models\MailingCampaign;
use App\Models\MailingContact;
use App\Models\MailingDelivery;
use App\Models\MailingLayout;
use App\Models\MailingLink;
use App\Models\MailingTemplate;
use App\Models\MailingTrigger;
use App\Models\Product;
use App\Services\Mailing\MailingSender;
use App\Services\Messaging\MessagingException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Emailing v back-office: kampane a automatizácie všetkých projektov.
 * Projekt do toho vstupuje len udalosťami cez API.
 *
 * Šablóny a layouty má MailingTemplateController, kontakty a štítky
 * MailingContactController.
 */
class MailingController extends Controller
{
    use SharesMailingData;

    public function __construct(private readonly MailingSender $sender) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'product' => ['nullable', 'exists:products,key'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $product = isset($filters['product']) ? Product::where('key', $filters['product'])->first() : null;
        $from = Carbon::parse($filters['from'] ?? now()->subDays(29)->toDateString())->startOfDay();
        $to = Carbon::parse($filters['to'] ?? now()->toDateString())->endOfDay();

        $deliveries = MailingDelivery::query()
            ->when($product, fn ($q) => $q->whereHas('campaign', fn ($c) => $c->where('product_id', $product->id)));
        $sent = (clone $deliveries)->whereBetween('sent_at', [$from, $to]);
        $contacts = MailingContact::query()->when($product, fn ($q) => $q->where('product_id', $product->id));

        $triggers = MailingTrigger::query()->get()->keyBy(fn ($t) => $t->product_id.':'.$t->key);

        return Inertia::render('Mailing/Index', [
            'campaigns' => MailingCampaign::query()
                ->with('product')
                ->withCount([
                    'deliveries as sent_count' => fn ($q) => $q->where('status', MailingDelivery::SENT),
                    'deliveries as pending_count' => fn ($q) => $q->where('status', MailingDelivery::PENDING),
                ])
                ->when($product, fn ($q) => $q->where('product_id', $product->id))
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (MailingCampaign $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'subject' => $c->subject,
                    'product' => $c->product?->name,
                    'status' => $c->status,
                    'trigger_event' => $c->trigger_event,
                    'trigger_label' => $triggers[$c->product_id.':'.$c->trigger_event]->short_label
                        ?? $triggers[$c->product_id.':'.$c->trigger_event]->label
                        ?? $c->trigger_event,
                    'delay_hours' => $c->delay_hours,
                    'reply_in_thread' => $c->isReply(),
                    'scheduled_at' => $c->scheduled_at?->toIso8601String(),
                    'sent_count' => $c->sent_count,
                    'pending_count' => $c->pending_count,
                ]),
            'stats' => [
                'sent' => (clone $sent)->count(),
                // Kohorta: reakcie na správy odoslané vo zvolenom období.
                'opened' => (clone $sent)->whereNotNull('opened_at')->count(),
                'clicked' => (clone $sent)->whereNotNull('clicked_at')->count(),
                'pending' => (clone $deliveries)->where('status', MailingDelivery::PENDING)->count(),
                'failed' => (clone $deliveries)->where('status', MailingDelivery::FAILED)->whereBetween('attempted_at', [$from, $to])->count(),
                // Doručiteľné kontakty = adresáti oznámení o službe; `active` sú tí so súhlasom s ponukami.
                'contacts' => (clone $contacts)->notBlocked()->count(),
                'active' => (clone $contacts)->reachable()->count(),
                'unsubscribed' => (clone $contacts)->whereNotNull('unsubscribed_at')->count(),
                'bounced' => (clone $contacts)->whereNotNull('bounced_at')->count(),
            ],
            'contacts_by_product' => $product ? [] : MailingContact::query()
                ->notBlocked()
                ->join('products', 'products.id', '=', 'mailing_contacts.product_id')
                ->groupBy('products.key', 'products.name')
                ->orderBy('products.name')
                ->get(['products.key', 'products.name', DB::raw('COUNT(*) as total')])
                ->map(fn ($row) => ['key' => $row->key, 'name' => $row->name, 'total' => (int) $row->total]),
            'filters' => ['product' => $product?->key, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'products' => $this->products(),
            'settings' => $this->settings(),
        ]);
    }

    public function create(Request $request): Response
    {
        $data = $request->validate([
            'product' => ['nullable', 'exists:products,key'],
            'template' => ['nullable', 'integer', 'exists:mailing_templates,id'],
        ]);

        $template = isset($data['template']) ? MailingTemplate::find($data['template']) : null;
        $product = $template?->product ?? (isset($data['product']) ? Product::where('key', $data['product'])->first() : null);

        return $this->editor(new MailingCampaign([
            'product_id' => $product?->id,
            'layout_id' => $template?->layout_id,
            'mailbox_id' => $template?->mailbox_id,
            'name' => $template?->name ?? '',
            'subject' => $template?->subject,
            'heading' => $template?->heading,
            'body' => $template?->body ?? '',
            'button_label' => $template?->button_label,
            'button_url' => $template?->button_url,
            'delay_hours' => 24,
            'track_clicks' => true,
            'quote_event' => true,
        ]));
    }

    public function edit(MailingCampaign $campaign): Response
    {
        return $this->editor($campaign);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedCampaign($request);

        $campaign = MailingCampaign::create([...$data, 'status' => MailingCampaign::DRAFT, 'created_by' => $request->user()->id]);

        return redirect()->route('mailing.edit', $campaign)->with('success', __('mailing.campaigns.saved'));
    }

    public function update(Request $request, MailingCampaign $campaign): RedirectResponse
    {
        if ($campaign->status !== MailingCampaign::DRAFT) {
            return back()->with('error', __('mailing.campaigns.only_draft'));
        }

        $campaign->update($this->validatedCampaign($request, $campaign));

        return back()->with('success', __('mailing.campaigns.saved'));
    }

    public function duplicate(MailingCampaign $campaign): RedirectResponse
    {
        $copy = $campaign->replicate(['status', 'scheduled_at']);
        $copy->name = __('mailing.campaigns.copy_of', ['name' => $campaign->name]);
        $copy->status = MailingCampaign::DRAFT;
        $copy->save();

        return redirect()->route('mailing.edit', $copy)->with('success', __('mailing.campaigns.duplicated'));
    }

    /**
     * Náhľad rozpracovaného formulára (bez uloženia). Premenné udalosti
     * ostanú ako {značky} – je hneď vidieť, kam projekt dosadí hodnotu.
     */
    public function preview(Request $request): JsonResponse
    {
        return response()->json(['html' => $this->sender->preview($this->draftFromRequest($request))]);
    }

    /** Test ide len na prihlasovací e-mail operátora. */
    public function test(Request $request): JsonResponse
    {
        $campaign = $this->draftFromRequest($request);

        try {
            $this->sender->sendTest($campaign, $request->user()->email);
        } catch (MessagingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => $this->sender->dryRun()
            ? __('mailing.campaigns.test_dry_run')
            : __('mailing.campaigns.test_sent', ['email' => $request->user()->email])]);
    }

    /** Koľko kontaktov by kampaň s týmto druhom a segmentom práve teraz oslovila. */
    public function audienceCount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_key' => ['required', 'exists:products,key'],
            ...self::audienceRules(),
        ]);

        $campaign = new MailingCampaign([
            'product_id' => Product::where('key', $data['product_key'])->value('id'),
            'kind' => $data['kind'] ?? MailingCampaign::OFFER,
            'audience' => self::audience($data),
        ]);

        return response()->json(['count' => MailingContact::audience($campaign)->count()]);
    }

    /**
     * Spustenie. Hromadná kampaň si v tejto chvíli zoberie zoznam
     * kontaktov podľa druhu a segmentu; automatizácia čaká na udalosti z projektu.
     */
    public function queue(Request $request, MailingCampaign $campaign): RedirectResponse
    {
        $data = $request->validate(['scheduled_at' => ['nullable', 'date', 'after_or_equal:now']]);

        $error = match (true) {
            ! $this->sender->enabled() => __('mailing.campaigns.disabled'),
            $campaign->status !== MailingCampaign::DRAFT => __('mailing.campaigns.already_queued'),
            $campaign->coupon_code && $campaign->couponExpired() => __('mailing.campaigns.coupon_expired'),
            $campaign->isAutomation() && ! empty($data['scheduled_at']) => __('mailing.campaigns.automation_no_schedule'),
            ! ($campaign->mailbox ?? Mailbox::defaultFor($campaign->product)) => __('messaging.errors.no_mailbox'),
            // Vybraná schránka bez hesla by poslala do prázdna – nepadá sa na predvolenú.
            $campaign->mailbox && ! $campaign->mailbox->is_active => __('messaging.errors.mailbox_inactive', ['address' => $campaign->mailbox->from_address]),
            ! $campaign->isAutomation() && ! MailingContact::audience($campaign)->exists() => __('mailing.campaigns.no_recipients'),
            default => null,
        };

        if ($error) {
            return back()->with('error', $error);
        }

        $total = DB::transaction(function () use ($campaign, $data) {
            $campaign = MailingCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            // Dvojklik na „Spustiť“: druhá požiadavka už nájde zaradenú kampaň.
            abort_unless($campaign->status === MailingCampaign::DRAFT, 409, __('mailing.campaigns.already_queued'));

            $total = 0;

            if (! $campaign->isAutomation()) {
                MailingContact::audience($campaign)->orderBy('id')
                    ->chunkById(500, function ($contacts) use ($campaign, &$total) {
                        $rows = $contacts->map(fn (MailingContact $c) => [
                            'campaign_id' => $campaign->id,
                            'contact_id' => $c->id,
                            'email' => $c->email,
                            'status' => MailingDelivery::PENDING,
                            'dedupe_key' => '',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ])->all();
                        DB::table('mailing_deliveries')->insertOrIgnore($rows);
                        $total += count($rows);
                    });
            }

            $campaign->forceFill([
                'status' => MailingCampaign::QUEUED,
                'scheduled_at' => ! empty($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : now(),
            ])->save();

            return $total;
        });

        return back()->with('success', $campaign->isAutomation()
            ? __('mailing.campaigns.activated')
            : trans_choice('mailing.campaigns.queued', $total, ['count' => $total]));
    }

    public function cancel(MailingCampaign $campaign): RedirectResponse
    {
        DB::transaction(function () use ($campaign) {
            $campaign = MailingCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            abort_unless($campaign->status === MailingCampaign::QUEUED, 422, __('mailing.campaigns.not_queued'));

            $campaign->forceFill(['status' => MailingCampaign::CANCELLED])->save();
            $campaign->deliveries()->where('status', MailingDelivery::PENDING)
                ->update(['status' => MailingDelivery::SKIPPED, 'error' => __('mailing.errors.campaign_stopped'), 'updated_at' => now()]);
        });

        return back()->with('success', __('mailing.campaigns.cancelled'));
    }

    /** Zmaže kampaň aj so záznamami odosielania; aktívnu treba najprv zastaviť. */
    public function destroy(MailingCampaign $campaign): RedirectResponse
    {
        if ($campaign->status === MailingCampaign::QUEUED) {
            return back()->with('error', __('mailing.campaigns.stop_first'));
        }

        $campaign->delete();

        return redirect()->route('mailing.index')->with('success', __('mailing.campaigns.deleted'));
    }

    public function show(Request $request, MailingCampaign $campaign): Response
    {
        $request->validate(['status' => ['nullable', 'string', 'max:10']]);

        return Inertia::render('Mailing/Show', [
            'campaign' => [
                ...$this->campaignPayload($campaign),
                'product' => $campaign->product?->name,
            ],
            'counts' => $campaign->deliveries()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'engagement' => [
                'opened' => $campaign->deliveries()->whereNotNull('opened_at')->count(),
                'clicked' => $campaign->deliveries()->whereNotNull('clicked_at')->count(),
            ],
            'links' => MailingLink::query()
                ->join('mailing_deliveries as d', 'd.id', '=', 'mailing_links.delivery_id')
                ->where('d.campaign_id', $campaign->id)
                ->selectRaw('mailing_links.url, SUM(mailing_links.click_count) as clicks, COUNT(DISTINCT CASE WHEN mailing_links.clicked_at IS NOT NULL THEN d.id END) as messages')
                ->groupBy('mailing_links.url')
                ->orderByDesc('clicks')
                ->get(),
            'deliveries' => $campaign->deliveries()
                ->with('contact:id,name')
                ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
                ->orderByDesc('id')
                ->paginate(50)
                ->withQueryString()
                ->through(fn (MailingDelivery $d) => [
                    'id' => $d->id,
                    'email' => $d->email,
                    'name' => $d->contact?->name ?? ($d->payload['name'] ?? null),
                    'status' => $d->status,
                    'ref' => $d->ref_type ? ($d->payload['ref_label'] ?? $d->ref_type.' '.$d->ref_id) : null,
                    'ref_url' => $d->payload['ref_url'] ?? null,
                    'due_at' => $d->due_at?->toIso8601String(),
                    'sent_at' => $d->sent_at?->toIso8601String(),
                    'opened_at' => $d->opened_at?->toIso8601String(),
                    'clicked_at' => $d->clicked_at?->toIso8601String(),
                    'error' => $d->error,
                ]),
            'filters' => ['status' => $request->input('status')],
        ]);
    }

    /** Uloží kampaň ako šablónu na neskôr. */
    public function saveAsTemplate(MailingCampaign $campaign): RedirectResponse
    {
        MailingTemplate::create($campaign->only(['product_id', 'layout_id', 'mailbox_id', 'name', 'subject', 'heading', 'body', 'button_label', 'button_url']));

        return back()->with('success', __('mailing.templates.saved'));
    }

    /* ---------------------------------------------------------------
     | Pomocné
     |---------------------------------------------------------------*/

    private function editor(MailingCampaign $campaign): Response
    {
        return Inertia::render('Mailing/Edit', [
            'campaign' => $this->campaignPayload($campaign),
            'products' => Product::query()->orderBy('name')
                ->with(['mailboxes' => fn ($q) => $q->active()->orderByDesc('is_default'), 'mailingTriggers', 'mailingTags', 'mailingLabels'])
                ->get()
                ->map(fn (Product $p) => [
                    'key' => $p->key,
                    'name' => $p->name,
                    'url' => $p->url,
                    'mailboxes' => $p->mailboxes->map(fn (Mailbox $m) => ['id' => $m->id, 'label' => $m->from_name ? "{$m->from_name} <{$m->from_address}>" : $m->from_address, 'address' => $m->from_address]),
                    'triggers' => $p->mailingTriggers->map(fn (MailingTrigger $t) => [
                        'key' => $t->key, 'label' => $t->label, 'variables' => $t->variables ?? [], 'quotes' => filled($t->quote_kind),
                    ]),
                    'tags' => $this->productTags($p),
                    // Atribúty, ktoré projekt posiela pri kontaktoch – nápoveda premenných.
                    'contact_variables' => $this->contactVariables($p),
                ]),
            'layouts' => MailingLayout::query()->with('product:id,key')->orderBy('name')->get()
                ->map(fn (MailingLayout $l) => ['id' => $l->id, 'name' => $l->name, 'product_key' => $l->product?->key]),
            'templates' => MailingTemplate::query()->with('product:id,key')->orderBy('name')->get()
                ->map(fn (MailingTemplate $t) => ['id' => $t->id, 'name' => $t->name, 'product_key' => $t->product?->key]),
            'settings' => $this->settings(),
        ]);
    }

    /** @return array<string, mixed> */
    private function campaignPayload(MailingCampaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'product_key' => $campaign->product?->key,
            'mailbox_id' => $campaign->mailbox_id,
            'layout_id' => $campaign->layout_id,
            'name' => $campaign->name,
            'kind' => $campaign->kind ?? MailingCampaign::OFFER,
            'audience' => [
                'any' => $campaign->audienceTags('any'),
                'none' => $campaign->audienceTags('none'),
            ],
            'subject' => $campaign->subject,
            'heading' => $campaign->heading,
            'body' => $campaign->body,
            'button_label' => $campaign->button_label,
            'button_url' => $campaign->button_url,
            'coupon_code' => $campaign->coupon_code,
            'coupon_valid_to' => $campaign->coupon_valid_to?->toDateString(),
            'status' => $campaign->status ?? MailingCampaign::DRAFT,
            'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
            'trigger_event' => $campaign->trigger_event,
            'delay_hours' => $campaign->delay_hours ?? 24,
            'track_clicks' => (bool) $campaign->track_clicks,
            'track_opens' => (bool) $campaign->track_opens,
            'reply_in_thread' => (bool) $campaign->reply_in_thread,
            'quote_event' => (bool) ($campaign->quote_event ?? true),
        ];
    }

    /**
     * Odpoveď vo vlákne preberá predmet z citovaného mailu a nemá nadpis,
     * tlačidlo, kupón ani meranie.
     *
     * @return array<string, mixed>
     */
    private function validatedCampaign(Request $request, ?MailingCampaign $campaign = null): array
    {
        $reply = $request->boolean('reply_in_thread') && $request->filled('trigger_event');

        $data = $request->validate([
            'product_key' => [$campaign ? 'nullable' : 'required', 'exists:products,key'],
            'mailbox_id' => ['nullable', 'integer', 'exists:mailboxes,id'],
            'layout_id' => ['nullable', 'integer', 'exists:mailing_layouts,id'],
            'name' => ['required', 'string', 'max:255'],
            'subject' => [$reply ? 'nullable' : 'required', 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'heading' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'button_label' => ['nullable', 'string', 'max:80'],
            'button_url' => ['nullable', 'url:http,https', 'max:2048'],
            'coupon_code' => ['nullable', 'string', 'max:60'],
            'coupon_valid_to' => ['nullable', 'date'],
            'trigger_event' => ['nullable', 'string', 'max:60'],
            'delay_hours' => ['required_with:trigger_event', 'integer', 'min:0', 'max:'.MailingCampaign::MAX_DELAY_HOURS],
            'track_clicks' => ['boolean'],
            'track_opens' => ['boolean'],
            'reply_in_thread' => ['boolean'],
            'quote_event' => ['boolean'],
            ...self::audienceRules(),
        ]);

        $product = isset($data['product_key']) ? Product::where('key', $data['product_key'])->firstOrFail() : $campaign->product;

        // Schránka, layout aj udalosť musia patriť tomu istému projektu.
        abort_if(isset($data['mailbox_id']) && ! Mailbox::whereKey($data['mailbox_id'])->where('product_id', $product->id)->exists(), 422);
        abort_if(isset($data['layout_id']) && ! MailingLayout::whereKey($data['layout_id'])->where('product_id', $product->id)->exists(), 422);
        abort_if(filled($data['trigger_event'] ?? null) && ! MailingTrigger::where('product_id', $product->id)->where('key', $data['trigger_event'])->exists(), 422);

        unset($data['product_key']);

        $data = [
            ...$data,
            'product_id' => $product->id,
            'kind' => $data['kind'] ?? MailingCampaign::OFFER,
            'audience' => self::audience($data),
            'trigger_event' => ($data['trigger_event'] ?? null) ?: null,
            'delay_hours' => $data['delay_hours'] ?? 24,
            'reply_in_thread' => $reply,
            'quote_event' => $request->boolean('quote_event', true),
        ];

        if ($reply) {
            $data = [...$data, 'heading' => null, 'button_label' => null, 'button_url' => null, 'coupon_code' => null,
                'coupon_valid_to' => null, 'track_clicks' => false, 'track_opens' => false, 'layout_id' => null];
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private static function audienceRules(): array
    {
        return [
            'kind' => ['nullable', Rule::in([MailingCampaign::OFFER, MailingCampaign::SERVICE])],
            'audience' => ['nullable', 'array'],
            'audience.any' => ['nullable', 'array', 'max:20'],
            'audience.any.*' => ['string', 'max:60'],
            'audience.none' => ['nullable', 'array', 'max:20'],
            'audience.none.*' => ['string', 'max:60'],
        ];
    }

    /**
     * Segment bez prázdnych častí; prázdny segment (všetci) je null.
     *
     * @param  array<string, mixed>  $data
     * @return array{any?: list<string>, none?: list<string>}|null
     */
    private static function audience(array $data): ?array
    {
        $audience = array_filter([
            'any' => array_values(array_unique($data['audience']['any'] ?? [])),
            'none' => array_values(array_unique($data['audience']['none'] ?? [])),
        ]);

        return $audience ?: null;
    }

    /** Neuložená kampaň z formulára – pre náhľad a test. */
    private function draftFromRequest(Request $request): MailingCampaign
    {
        $campaign = new MailingCampaign($this->validatedCampaign($request));
        $campaign->setRelation('product', Product::find($campaign->product_id));
        $campaign->setRelation('layout', $campaign->layout_id ? MailingLayout::find($campaign->layout_id) : null);
        $campaign->setRelation('mailbox', $campaign->mailbox_id ? Mailbox::find($campaign->mailbox_id) : null);

        return $campaign;
    }

    /** @return list<string> */
    private function contactVariables(Product $product): array
    {
        return MailingContact::query()->where('product_id', $product->id)->whereNotNull('attributes')
            ->latest('updated_at')->limit(50)->pluck('attributes')
            ->flatMap(fn ($attributes) => array_keys((array) $attributes))
            ->unique()->sort()->values()->all();
    }
}
