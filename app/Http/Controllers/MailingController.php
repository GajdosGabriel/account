<?php

namespace App\Http\Controllers;

use App\Models\Mailbox;
use App\Models\MailingCampaign;
use App\Models\MailingContact;
use App\Models\MailingDelivery;
use App\Models\MailingLabel;
use App\Models\MailingLayout;
use App\Models\MailingLink;
use App\Models\MailingTag;
use App\Models\MailingTemplate;
use App\Models\MailingTrigger;
use App\Models\MailSuppression;
use App\Models\Product;
use App\Services\Mailing\BounceClassifier;
use App\Services\Mailing\MailingRenderer;
use App\Services\Mailing\MailingSender;
use App\Services\Messaging\MessagingException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Emailing v back-office: kampane, automatizácie, šablóny, layouty a kontakty
 * všetkých projektov. Projekt do toho vstupuje len udalosťami cez API.
 */
class MailingController extends Controller
{
    public function __construct(private readonly MailingSender $sender) {}

    /* ---------------------------------------------------------------
     | Kampane
     |---------------------------------------------------------------*/

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

    /* ---------------------------------------------------------------
     | Šablóny a layouty
     |---------------------------------------------------------------*/

    public function templates(Request $request): Response
    {
        $product = $request->filled('product') ? Product::where('key', $request->input('product'))->first() : null;

        return Inertia::render('Mailing/Templates', [
            'templates' => MailingTemplate::query()->with(['product', 'layout', 'mailbox'])
                ->when($product, fn ($q) => $q->where('product_id', $product->id))
                ->orderBy('name')->get()
                ->map(fn (MailingTemplate $t) => [
                    ...$t->only(['id', 'name', 'subject', 'heading', 'body', 'button_label', 'button_url', 'layout_id', 'mailbox_id']),
                    'product_key' => $t->product?->key,
                    'product' => $t->product?->name,
                    'layout' => $t->layout?->name,
                    'mailbox' => $t->mailbox?->from_address,
                ]),
            // Aj vypnuté – šablóna môže čakať na schránku, ktorej správca ešte nedoplnil heslo.
            'mailboxes' => Mailbox::query()->with('product:id,key')
                ->when($product, fn ($q) => $q->where('product_id', $product->id))
                ->orderByDesc('is_default')->orderBy('id')->get()
                ->map(fn (Mailbox $m) => [
                    'id' => $m->id,
                    'product_key' => $m->product?->key,
                    'label' => ($m->from_name ? "{$m->from_name} <{$m->from_address}>" : $m->from_address).($m->is_active ? '' : ' – '.__('messaging.mailboxes.inactive_badge')),
                ]),
            'layouts' => MailingLayout::query()->with('product')
                ->when($product, fn ($q) => $q->where('product_id', $product->id))
                ->orderBy('name')->get()
                ->map(fn (MailingLayout $l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'html' => $l->html,
                    'product_key' => $l->product?->key,
                    'product' => $l->product?->name,
                ]),
            'default_layout' => MailingRenderer::defaultLayout(),
            'filters' => ['product' => $product?->key],
            'products' => $this->products(),
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        MailingTemplate::create($this->validatedTemplate($request));

        return back()->with('success', __('mailing.templates.saved'));
    }

    public function updateTemplate(Request $request, MailingTemplate $template): RedirectResponse
    {
        $template->update($this->validatedTemplate($request));

        return back()->with('success', __('mailing.templates.saved'));
    }

    public function destroyTemplate(MailingTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('success', __('mailing.templates.deleted'));
    }

    /** Uloží kampaň ako šablónu na neskôr. */
    public function saveAsTemplate(MailingCampaign $campaign): RedirectResponse
    {
        MailingTemplate::create($campaign->only(['product_id', 'layout_id', 'mailbox_id', 'name', 'subject', 'heading', 'body', 'button_label', 'button_url']));

        return back()->with('success', __('mailing.templates.saved'));
    }

    public function storeLayout(Request $request): RedirectResponse
    {
        MailingLayout::create($this->validatedLayout($request));

        return back()->with('success', __('mailing.layouts.saved'));
    }

    public function updateLayout(Request $request, MailingLayout $layout): RedirectResponse
    {
        $layout->update($this->validatedLayout($request));

        return back()->with('success', __('mailing.layouts.saved'));
    }

    public function destroyLayout(MailingLayout $layout): RedirectResponse
    {
        if (MailingCampaign::where('layout_id', $layout->id)->where('status', MailingCampaign::QUEUED)->exists()) {
            return back()->with('error', __('mailing.layouts.in_use'));
        }

        $layout->delete();

        return back()->with('success', __('mailing.layouts.deleted'));
    }

    /* ---------------------------------------------------------------
     | Kontakty
     |---------------------------------------------------------------*/

    public function contacts(Request $request): Response
    {
        $filters = $this->contactFilters($request);
        $product = isset($filters['product']) ? Product::where('key', $filters['product'])->first() : null;

        $contacts = $this->contactsQuery($filters)->with('product')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        // Blokované adresy len pre zobrazenú stranu.
        $suppressed = MailSuppression::query()
            ->whereIn('email', $contacts->pluck('email'))
            ->get(['product_id', 'email', 'reason'])
            ->keyBy(fn (MailSuppression $s) => $s->product_id.':'.$s->email);

        $labels = MailingLabel::query()->with('product:id,key,name')->orderBy('name')->get();
        $counts = $this->labelCounts();
        $labelOf = $labels->keyBy(fn (MailingLabel $l) => $l->product_id.':'.$l->key);

        return Inertia::render('Mailing/Contacts', [
            'contacts' => $contacts->through(fn (MailingContact $c) => [
                'id' => $c->id,
                'email' => $c->email,
                'name' => $c->name,
                'product' => $c->product?->name,
                'product_key' => $c->product?->key,
                'tags' => $c->tags ?? [],
                'manual_tags' => collect($c->manual_tags ?? [])->map(fn ($key) => [
                    'key' => $key,
                    'name' => $labelOf[$c->product_id.':'.$key]->name ?? $key,
                    'color' => $labelOf[$c->product_id.':'.$key]->color ?? 'indigo',
                ])->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values(),
                'permission_note' => $c->permission_note,
                'permission_at' => $c->permission_at?->toIso8601String(),
                'unsubscribed_at' => $c->unsubscribed_at?->toIso8601String(),
                'bounced_at' => $c->bounced_at?->toIso8601String(),
                'bounce_reason' => $c->bounce_reason,
                'suppression' => $suppressed[$c->product_id.':'.$c->email]->reason ?? null,
            ]),
            'filters' => ['product' => $product?->key, 'search' => $filters['search'] ?? '', 'state' => $filters['state'] ?? null, 'tag' => $filters['tag'] ?? null],
            'tags' => $product ? $this->productTags($product->load('mailingTags', 'mailingLabels')) : [],
            'labels' => $labels->map(fn (MailingLabel $l) => [
                'id' => $l->id,
                'key' => $l->key,
                'name' => $l->name,
                'color' => $l->color,
                'product_key' => $l->product?->key,
                'product' => $l->product?->name,
                'count' => $counts[$l->product_id.':'.$l->key] ?? 0,
            ]),
            'colors' => MailingLabel::COLORS,
            'products' => $this->products(),
        ]);
    }

    /**
     * Pridanie a import kontaktov. Riadok „meno <adresa>“, len adresa alebo
     * riadok z CSV („adresa;meno“, „meno,adresa“ …); riadky bez adresy
     * (napr. hlavička CSV) sa preskočia. CSV s hlavičkou (aj export
     * kontaktov) sa číta podľa stĺpcov vrátane stĺpca so štítkami.
     *
     * Kontakt pridaný v Accounte má predvolene súhlas s ponukami. Existujúci
     * kontakt dostane zvolené štítky a – ak súhlas chýbal – aj súhlas;
     * odhlásenie sa nemení.
     */
    public function storeContacts(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_key' => ['required', 'exists:products,key'],
            'emails' => ['required', 'string', 'max:2000000'],
            'consent' => ['boolean'],
            'permission_note' => ['nullable', 'string', 'max:255'],
            'labels' => ['nullable', 'array', 'max:'.MailingLabel::MAX_PER_CONTACT],
            'labels.*' => ['string', 'max:60'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'with_names' => ['boolean'],
            'with_file_labels' => ['boolean'],
        ]);

        $product = Product::where('key', $data['product_key'])->firstOrFail();
        $consent = (bool) ($data['consent'] ?? true);
        $note = trim((string) ($data['permission_note'] ?? '')) ?: __('mailing.contacts.permission_default', ['user' => $request->user()->name]);
        $labels = MailingLabel::resolve($product->id, [...($data['labels'] ?? []), ...preg_split('/[,;]+/', (string) ($data['tags'] ?? ''))]);
        [$rows, $skipped] = self::parseContacts($data['emails']);

        // Voliteľne len adresy – meno a štítky zo súboru sa vynechajú.
        $withNames = $request->boolean('with_names', true);
        $withFileLabels = $request->boolean('with_file_labels', true);
        $rows = array_map(fn (array $row) => [
            'name' => $withNames ? $row['name'] : null,
            'labels' => $withFileLabels ? $row['labels'] : [],
        ], $rows);
        $added = 0;
        $updated = 0;
        $unchanged = 0;

        // Štítky zo stĺpca CSV (napr. z exportu iného projektu) – rovnaké názvy len raz.
        $rowLabels = [];
        $labelsOf = function (array $names) use ($product, &$rowLabels) {
            return $names ? ($rowLabels[implode("\n", $names)] ??= MailingLabel::resolve($product->id, $names)) : [];
        };

        DB::transaction(function () use ($rows, $product, $consent, $note, $labels, $labelsOf, $request, &$added, &$updated, &$unchanged) {
            foreach ($rows as $email => ['name' => $name, 'labels' => $names]) {
                $contact = MailingContact::withTrashed()->firstOrNew(['product_id' => $product->id, 'email' => $email]);
                $tags = self::mergeLabels($contact->manual_tags ?? [], self::mergeLabels($labels, $labelsOf($names)));

                if ($contact->exists && ! $contact->trashed()) {
                    $contact->manual_tags = $tags ?: null;
                    $contact->name ??= $name;

                    if ($consent && ! $contact->hasConsent()) {
                        $contact->fill(['permission_note' => $note, 'permission_at' => now()]);
                    }

                    $contact->isDirty() ? $updated++ : $unchanged++;
                    $contact->save();

                    continue;
                }

                // Kontakt odstránený projektom sa obnoví aj s odhlásením.
                $contact->deleted_at = null;
                $contact->fill([
                    'name' => $name ?? $contact->name,
                    'created_by' => $request->user()->id,
                    'manual_tags' => $tags ?: null,
                ]);

                if ($consent && ! $contact->hasConsent()) {
                    $contact->fill(['permission_note' => $note, 'permission_at' => now()]);
                }

                $contact->save();
                $added++;
            }
        });

        return back()->with('success', __('mailing.contacts.imported', [
            'added' => $added, 'updated' => $updated, 'unchanged' => $unchanged, 'skipped' => $skipped,
        ]));
    }

    /**
     * Export kontaktov do CSV – vybrané alebo všetky podľa filtra. Súbor
     * sa dá naspäť načítať importom (aj do iného projektu) vrátane štítkov.
     */
    public function exportContacts(Request $request): StreamedResponse
    {
        $data = $request->validate([
            ...$this->contactFilterRules(),
            'ids' => ['nullable', 'array', 'max:1000'],
            'ids.*' => ['integer'],
        ]);

        $query = ! empty($data['ids'])
            ? MailingContact::query()->whereIn('id', $data['ids'])
            : $this->contactsQuery($data);
        $labels = MailingLabel::query()->get()->keyBy(fn (MailingLabel $l) => $l->product_id.':'.$l->key);
        $filename = 'kontakty-'.($data['product'] ?? 'vsetky').($data['tag'] ?? null ? '-'.Str::slug($data['tag']) : '').'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query, $labels) {
            $out = fopen('php://output', 'w');

            // BOM, aby Excel na Windows nerozbil diakritiku.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['email', 'meno', 'stitky', 'stitky_projektu', 'projekt', 'suhlas', 'suhlas_od', 'stav'], ';', '"', '');

            $query->with('product:id,key,name')->chunkById(500, function ($contacts) use ($out, $labels) {
                $suppressed = MailSuppression::query()->whereIn('email', $contacts->pluck('email'))->get(['product_id', 'email'])
                    ->mapWithKeys(fn (MailSuppression $s) => [$s->product_id.':'.$s->email => true]);

                foreach ($contacts as $c) {
                    $state = match (true) {
                        (bool) $c->unsubscribed_at => 'odhlaseny',
                        $c->bounced_at || isset($suppressed[$c->product_id.':'.$c->email]) => 'nedorucitelny',
                        ! $c->hasConsent() => 'len_oznamenia',
                        default => 'aktivny',
                    };

                    fputcsv($out, array_map(self::csvSafe(...), [
                        $c->email,
                        $c->name,
                        collect($c->manual_tags ?? [])->map(fn ($key) => $labels[$c->product_id.':'.$key]->name ?? $key)->sort(SORT_NATURAL | SORT_FLAG_CASE)->implode(', '),
                        implode(', ', $c->tags ?? []),
                        $c->product?->key,
                        $c->permission_note,
                        $c->permission_at?->toDateString(),
                        $state,
                    ]), ';', '"', '');
                }
            }, 'mailing_contacts.id', 'id');

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Bunka nezačne vzorcom – Excel by ju inak vykonal. Import apostrof odreže. */
    private static function csvSafe(?string $value): ?string
    {
        return $value !== null && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /** Ručné štítky kontaktu; štítky z projektu sa tu nemenia. */
    public function updateContactTags(Request $request, MailingContact $contact): RedirectResponse
    {
        $data = $request->validate([
            'labels' => ['nullable', 'array', 'max:'.MailingLabel::MAX_PER_CONTACT],
            'labels.*' => ['string', 'max:60'],
            'tags' => ['nullable', 'string', 'max:1000'],
        ]);

        $keys = MailingLabel::resolve($contact->product_id, $data['labels'] ?? $data['tags'] ?? null);
        $contact->forceFill(['manual_tags' => $keys ?: null])->save();

        return back()->with('success', __('mailing.contacts.tags_saved'));
    }

    /**
     * Hromadná akcia nad vybranými kontaktmi alebo nad všetkými, ktoré
     * zodpovedajú filtru (aj na ďalších stranách).
     */
    public function bulkContacts(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['add_labels', 'remove_labels', 'consent', 'unsubscribe', 'delete', 'copy'])],
            'all' => ['boolean'],
            'ids' => ['required_unless:all,true', 'array', 'max:1000'],
            'ids.*' => ['integer'],
            'filters' => ['nullable', 'array'],
            'labels' => ['required_if:action,add_labels,remove_labels', 'array', 'max:'.MailingLabel::MAX_PER_CONTACT],
            'labels.*' => ['string', 'max:60'],
            'product_key' => ['required_if:action,copy', 'nullable', 'exists:products,key'],
            'with_labels' => ['boolean'],
            'consent' => ['boolean'],
        ]);

        $query = ($data['all'] ?? false)
            ? $this->contactsQuery(validator($data['filters'] ?? [], $this->contactFilterRules())->validate())
            : MailingContact::query()->whereIn('id', $data['ids'] ?? []);

        if ($data['action'] === 'copy') {
            return $this->copyContacts($request, $query, $data);
        }

        $note = __('mailing.contacts.permission_default', ['user' => $request->user()->name]);
        $keys = [];
        $label = function (int $productId) use (&$keys, $data) {
            return $keys[$productId] ??= MailingLabel::resolve($productId, $data['labels'] ?? [], $data['action'] === 'add_labels');
        };
        $count = 0;

        DB::transaction(function () use ($query, $data, $note, $label, &$count) {
            $query->chunkById(500, function ($contacts) use ($data, $note, $label, &$count) {
                foreach ($contacts as $contact) {
                    $changed = match ($data['action']) {
                        'add_labels' => $this->setLabels($contact, self::mergeLabels($contact->manual_tags ?? [], $label($contact->product_id))),
                        'remove_labels' => $this->setLabels($contact, array_values(array_diff($contact->manual_tags ?? [], $label($contact->product_id)))),
                        'consent' => ! $contact->hasConsent() && $contact->forceFill(['permission_note' => $note, 'permission_at' => now()])->save(),
                        'unsubscribe' => ! $contact->unsubscribed_at && $this->unsubscribe($contact),
                        'delete' => (bool) $contact->forceDelete(),
                    };

                    $count += (int) $changed;
                }
            }, 'mailing_contacts.id', 'id');
        });

        return back()->with('success', __('mailing.contacts.bulk_done.'.$data['action'], ['count' => $count]));
    }

    /**
     * Kópia kontaktov do iného projektu. Odhlásené a nedoručiteľné adresy
     * sa nekopírujú. Súhlas prejde len kontaktom, ktoré ho majú v pôvodnom
     * projekte; v cieľovom projekte sa poznačí, odkiaľ kontakt prišiel.
     * Existujúci kontakt v cieli dostane štítky, odhlásenie sa nemení.
     *
     * @param  Builder<MailingContact>  $query
     * @param  array<string, mixed>  $data
     */
    private function copyContacts(Request $request, Builder $query, array $data): RedirectResponse
    {
        $target = Product::where('key', $data['product_key'])->firstOrFail();
        $total = (clone $query)->count();
        $query->where('mailing_contacts.product_id', '!=', $target->id)->whereNull('unsubscribed_at')->notBlocked();

        $consent = (bool) ($data['consent'] ?? true);
        $withLabels = (bool) ($data['with_labels'] ?? true);
        $extra = MailingLabel::resolve($target->id, $data['labels'] ?? []);
        $names = MailingLabel::query()->get()->keyBy(fn (MailingLabel $l) => $l->product_id.':'.$l->key);
        $products = Product::query()->pluck('name', 'id');
        $resolved = [];
        $copied = 0;
        $existing = 0;

        // Ručné štítky sa v cieli nájdu (alebo vytvoria) podľa názvu.
        $labelsFor = function (MailingContact $c) use ($withLabels, $extra, $names, $target, &$resolved): array {
            if (! $withLabels || ! $c->manual_tags) {
                return $extra;
            }

            $source = collect($c->manual_tags)->map(fn ($key) => $names[$c->product_id.':'.$key]->name ?? $key)->all();

            return self::mergeLabels($extra, $resolved[$c->product_id.':'.implode(',', $c->manual_tags)] ??= MailingLabel::resolve($target->id, $source));
        };

        DB::transaction(function () use ($query, $target, $consent, $labelsFor, $products, $request, &$copied, &$existing) {
            $query->chunkById(500, function ($contacts) use ($target, $consent, $labelsFor, $products, $request, &$copied, &$existing) {
                foreach ($contacts as $source) {
                    $contact = MailingContact::withTrashed()->firstOrNew(['product_id' => $target->id, 'email' => $source->email]);
                    $existing += (int) ($contact->exists && ! $contact->trashed());

                    $contact->deleted_at = null;
                    $contact->name ??= $source->name;
                    $contact->created_by ??= $request->user()->id;
                    $contact->manual_tags = self::mergeLabels($contact->manual_tags ?? [], $labelsFor($source)) ?: null;

                    if ($consent && $source->hasConsent() && ! $contact->hasConsent()) {
                        $contact->fill([
                            'permission_note' => Str::limit(__('mailing.contacts.permission_copied', [
                                'product' => $products[$source->product_id] ?? '?',
                                'note' => $source->permission_note ?? $source->permission_at?->toDateString(),
                            ]), 255, ''),
                            'permission_at' => now(),
                        ]);
                    }

                    $contact->save();
                    $copied++;
                }
            }, 'mailing_contacts.id', 'id');
        });

        return back()->with('success', __('mailing.contacts.bulk_done.copy', [
            'product' => $target->name, 'count' => $copied, 'existing' => $existing, 'skipped' => $total - $copied,
        ]));
    }

    public function unsubscribeContact(MailingContact $contact): RedirectResponse
    {
        if (! $contact->unsubscribed_at) {
            $this->unsubscribe($contact);
        }

        return back()->with('success', __('mailing.contacts.unsubscribed'));
    }

    /**
     * Vylúčenú adresu vráti späť (adresa opravená, schránka uvoľnená).
     * Odhlásenie človeka sa tu nezruší – to musí urobiť on sám.
     */
    public function reactivateContact(MailingContact $contact): RedirectResponse
    {
        $contact->forceFill(['bounced_at' => null, 'bounce_reason' => null, 'soft_bounces' => 0])->save();

        MailSuppression::query()->where('product_id', $contact->product_id)->where('email', $contact->email)
            ->where('reason', MailSuppression::HARD_BOUNCE)->delete();

        return back()->with('success', __('mailing.contacts.reactivated'));
    }

    /** Ručné zmazanie je trvalé – na rozdiel od odstránenia projektom. */
    public function destroyContact(MailingContact $contact): RedirectResponse
    {
        $contact->forceDelete();

        return back()->with('success', __('mailing.contacts.deleted'));
    }

    /* ---------------------------------------------------------------
     | Ručné štítky
     |---------------------------------------------------------------*/

    public function storeLabel(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_key' => ['required', 'exists:products,key'],
            'name' => ['required', 'string', 'max:60', 'regex:/[\pL\pN]/u'],
            'color' => ['required', Rule::in(MailingLabel::COLORS)],
        ]);

        $product = Product::where('key', $data['product_key'])->firstOrFail();
        $this->ensureUniqueLabel($product->id, $data['name']);

        MailingLabel::create(['product_id' => $product->id, 'key' => Str::limit(Str::slug($data['name']), 60, ''), 'name' => trim($data['name']), 'color' => $data['color']]);

        return back()->with('success', __('mailing.labels.created'));
    }

    /** Premenovanie mení len názov – kľúč pri kontaktoch a v kampaniach ostáva. */
    public function updateLabel(Request $request, MailingLabel $label): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'regex:/[\pL\pN]/u'],
            'color' => ['required', Rule::in(MailingLabel::COLORS)],
        ]);

        $this->ensureUniqueLabel($label->product_id, $data['name'], $label);
        $label->update(['name' => trim($data['name']), 'color' => $data['color']]);

        return back()->with('success', __('mailing.labels.updated'));
    }

    /** Štítok zmizne aj zo všetkých kontaktov; kontakty samotné ostávajú. */
    public function destroyLabel(MailingLabel $label): RedirectResponse
    {
        DB::transaction(function () use ($label) {
            MailingContact::withTrashed()->where('product_id', $label->product_id)->withManualTag($label->key)
                ->chunkById(500, fn ($contacts) => $contacts->each(fn (MailingContact $c) => $this->setLabels($c, array_values(array_diff($c->manual_tags ?? [], [$label->key])))));

            $label->delete();
        });

        return back()->with('success', __('mailing.labels.deleted'));
    }

    /** Ručné nahlásenie nedoručiteľných adries (napr. zo správy o nedoručení). */
    public function bounce(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_key' => ['required', 'exists:products,key'],
            'emails' => ['required', 'string', 'max:50000'],
            'type' => ['required', Rule::in([BounceClassifier::HARD, BounceClassifier::SOFT])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $product = Product::where('key', $data['product_key'])->firstOrFail();
        $suppressed = 0;

        foreach (preg_split('/[\s,;]+/', trim($data['emails']), -1, PREG_SPLIT_NO_EMPTY) as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $suppressed += (int) $this->sender->recordBounce($product, $email, $data['type'], $data['reason'] ?? __('mailing.contacts.bounce_manual'));
            }
        }

        return back()->with('success', __('mailing.contacts.bounced', ['count' => $suppressed]));
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
                    'mailboxes' => $p->mailboxes->map(fn (Mailbox $m) => ['id' => $m->id, 'label' => $m->from_name ? "{$m->from_name} <{$m->from_address}>" : $m->from_address]),
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

    /** @return array<string, mixed> */
    private function validatedTemplate(Request $request): array
    {
        $data = $request->validate([
            'product_key' => ['required', 'exists:products,key'],
            'layout_id' => ['nullable', 'integer', 'exists:mailing_layouts,id'],
            'mailbox_id' => ['nullable', 'integer', 'exists:mailboxes,id'],
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'heading' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'button_label' => ['nullable', 'string', 'max:80'],
            'button_url' => ['nullable', 'url:http,https', 'max:2048'],
        ]);

        $product = Product::where('key', $data['product_key'])->firstOrFail();
        abort_if(isset($data['layout_id']) && ! MailingLayout::whereKey($data['layout_id'])->where('product_id', $product->id)->exists(), 422);
        abort_if(isset($data['mailbox_id']) && ! Mailbox::whereKey($data['mailbox_id'])->where('product_id', $product->id)->exists(), 422);
        unset($data['product_key']);

        return [...$data, 'product_id' => $product->id];
    }

    /** @return array<string, mixed> */
    private function validatedLayout(Request $request): array
    {
        $data = $request->validate([
            'product_key' => ['required', 'exists:products,key'],
            'name' => ['required', 'string', 'max:120'],
            'html' => ['required', 'string', 'max:200000'],
        ]);

        $product = Product::where('key', $data['product_key'])->firstOrFail();

        return ['product_id' => $product->id, 'name' => $data['name'], 'html' => $data['html']];
    }

    /** @return array<string, list<mixed>> */
    private function contactFilterRules(): array
    {
        return [
            'product' => ['nullable', 'exists:products,key'],
            'search' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', Rule::in(['active', 'service', 'unsubscribed', 'bounced'])],
            'tag' => ['nullable', 'string', 'max:60'],
        ];
    }

    /** @return array<string, mixed> */
    private function contactFilters(Request $request): array
    {
        return $request->validate($this->contactFilterRules());
    }

    /**
     * Kontakty podľa filtra v zozname – rovnaký výber pre zobrazenie
     * aj pre hromadnú akciu „všetky podľa filtra“.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<MailingContact>
     */
    private function contactsQuery(array $filters): Builder
    {
        $product = isset($filters['product']) ? Product::where('key', $filters['product'])->first() : null;
        $state = $filters['state'] ?? null;

        return MailingContact::query()
            ->when($product, fn ($q) => $q->where('mailing_contacts.product_id', $product->id))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('email', 'like', '%'.$s.'%')->orWhere('name', 'like', '%'.$s.'%')))
            ->when($filters['tag'] ?? null, fn ($q, $tag) => $q->withTag($tag))
            ->when($state === 'active', fn ($q) => $q->reachable())
            // Bez súhlasu s ponukami: dostanú len oznámenia o službe.
            ->when($state === 'service', fn ($q) => $q->whereNull('permission_at')->whereNull('permission_note')->notBlocked())
            ->when($state === 'unsubscribed', fn ($q) => $q->whereNotNull('unsubscribed_at'))
            ->when($state === 'bounced', fn ($q) => $q->where(fn ($q) => $q->whereNotNull('bounced_at')
                ->orWhereExists(fn ($s) => $s->from('mail_suppressions')
                    ->whereColumn('mail_suppressions.product_id', 'mailing_contacts.product_id')
                    ->whereColumn('mail_suppressions.email', 'mailing_contacts.email'))));
    }

    /**
     * Adresy z vloženého textu alebo CSV: [adresa => [meno|null, názvy štítkov]]
     * bez duplicít a počet neplatných adries.
     *
     * @return array{0: array<string, array{name: ?string, labels: list<string>}>, 1: int}
     */
    private static function parseContacts(string $text): array
    {
        $rows = [];
        $skipped = 0;
        $columns = null;

        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim(preg_replace('/^\x{FEFF}/u', '', $line));

            // Riadok bez zavináča (hlavička CSV, prázdny riadok) nie je kontakt.
            if (! str_contains($line, '@')) {
                $columns ??= self::csvHeader($line);

                continue;
            }

            if ($columns && ($row = self::csvRow($line, $columns))) {
                [$email, $name, $labels] = $row;
                $email = strtolower(trim($email, " \t\"'<>"));

                if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
                    $skipped++;

                    continue;
                }

                $rows[$email] ??= ['name' => $name ? Str::limit($name, 255, '') : null, 'labels' => $labels];

                continue;
            }

            $fields = array_values(array_filter(array_map(
                fn ($field) => trim($field, " \t\"'"),
                preg_split('/[,;\t]+/', $line),
            ), fn ($field) => $field !== ''));
            $emails = [];
            $names = [];

            foreach ($fields as $field) {
                // „Jana Nová <jana@firma.sk>“
                if (preg_match('/^(.*?)<\s*([^<>\s]+@[^<>\s]+)\s*>$/u', $field, $m)) {
                    $emails[] = $m[2];
                    $names[] = trim($m[1], " \t\"'");
                } elseif (str_contains($field, '@')) {
                    $emails[] = $field;
                } else {
                    $names[] = $field;
                }
            }

            // Jedna adresa v riadku: meno je prvé ďalšie pole; viac adries je len zoznam.
            $name = count($emails) === 1 ? (collect($names)->first(fn ($n) => $n !== '' && ! is_numeric($n))) : null;

            foreach ($emails as $email) {
                $email = strtolower(trim($email, " \t\"'<>"));

                if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
                    $skipped++;

                    continue;
                }

                $rows[$email] ??= ['name' => $name ? Str::limit($name, 255, '') : null, 'labels' => []];
            }
        }

        return [$rows, $skipped];
    }

    /**
     * Hlavička CSV so stĺpcom adresy: oddeľovač a poradie stĺpcov adresa,
     * meno a štítky. Bez stĺpca adresy null – riadky sa čítajú voľne.
     *
     * @return array{delimiter: string, email: int, name: ?int, labels: ?int}|null
     */
    private static function csvHeader(string $line): ?array
    {
        if ($line === '') {
            return null;
        }

        $delimiter = collect([';', "\t", ','])->sortByDesc(fn ($d) => substr_count($line, $d))->first();
        $keys = array_map(fn ($field) => Str::slug((string) $field), str_getcsv($line, $delimiter, '"', ''));
        $find = function (array $names) use ($keys): ?int {
            $index = array_search(true, array_map(fn ($key) => in_array($key, $names, true), $keys), true);

            return $index === false ? null : $index;
        };
        $email = $find(['email', 'e-mail', 'mail', 'adresa', 'emailova-adresa']);

        if ($email === null) {
            return null;
        }

        return [
            'delimiter' => $delimiter,
            'email' => $email,
            'name' => $find(['meno', 'name', 'meno-a-priezvisko', 'nazov', 'kontakt']),
            'labels' => $find(['stitky', 'stitok', 'labels', 'label', 'tags']),
        ];
    }

    /**
     * Riadok CSV podľa hlavičky: [adresa, meno, názvy štítkov]. Null, keď
     * v stĺpci adresy nie je platná adresa – riadok sa potom číta voľne.
     *
     * @param  array{delimiter: string, email: int, name: ?int, labels: ?int}  $columns
     * @return array{0: string, 1: ?string, 2: list<string>}|null
     */
    private static function csvRow(string $line, array $columns): ?array
    {
        $fields = array_map(fn ($field) => trim((string) $field, " \t\"'"), str_getcsv($line, $columns['delimiter'], '"', ''));
        $email = $fields[$columns['email']] ?? '';

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $name = $columns['name'] !== null ? ($fields[$columns['name']] ?? '') : '';
        $labels = $columns['labels'] !== null ? preg_split('/[,;|]+/', $fields[$columns['labels']] ?? '') : [];

        return [
            $email,
            $name !== '' ? $name : null,
            array_values(array_filter(array_map('trim', $labels), fn ($label) => $label !== '')),
        ];
    }

    /**
     * @param  list<string>  $current
     * @param  list<string>  $add
     * @return list<string>
     */
    private static function mergeLabels(array $current, array $add): array
    {
        $tags = array_values(array_unique([...$current, ...$add]));
        sort($tags);

        return array_slice($tags, 0, MailingLabel::MAX_PER_CONTACT);
    }

    /** @param list<string> $keys */
    private function setLabels(MailingContact $contact, array $keys): bool
    {
        sort($keys);

        if ($keys === ($contact->manual_tags ?? [])) {
            return false;
        }

        return $contact->forceFill(['manual_tags' => $keys ?: null])->save();
    }

    private function unsubscribe(MailingContact $contact): bool
    {
        $contact->forceFill(['unsubscribed_at' => now()])->save();
        MailingDelivery::query()->where('contact_id', $contact->id)->where('status', MailingDelivery::PENDING)
            ->update(['status' => MailingDelivery::SKIPPED, 'error' => __('mailing.errors.unsubscribed'), 'updated_at' => now()]);

        return true;
    }

    /** @return array<string, int> počet kontaktov podľa „produkt:kľúč“ */
    private function labelCounts(): array
    {
        $counts = [];

        MailingContact::query()->whereNotNull('manual_tags')->select(['id', 'product_id', 'manual_tags'])
            ->chunkById(2000, function ($contacts) use (&$counts) {
                foreach ($contacts as $contact) {
                    foreach ($contact->manual_tags ?? [] as $key) {
                        $counts[$contact->product_id.':'.$key] = ($counts[$contact->product_id.':'.$key] ?? 0) + 1;
                    }
                }
            });

        return $counts;
    }

    /** Dva štítky projektu nesmú mať rovnaký názov ani kľúč. */
    private function ensureUniqueLabel(int $productId, string $name, ?MailingLabel $ignore = null): void
    {
        $name = trim($name);
        $taken = MailingLabel::query()->where('product_id', $productId)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->get()
            ->contains(fn (MailingLabel $l) => mb_strtolower($l->name) === mb_strtolower($name) || (! $ignore && $l->key === Str::slug($name)));

        if ($taken) {
            throw ValidationException::withMessages(['name' => __('mailing.labels.taken')]);
        }
    }

    /**
     * Štítky projektu: zaregistrované s popisom a k nim tie, ktoré kontakty
     * majú, hoci ich projekt (zatiaľ) nezaregistroval.
     *
     * @return list<array{key: string, label: string, description: ?string}>
     */
    private function productTags(Product $product): array
    {
        $tags = $product->mailingTags->mapWithKeys(fn (MailingTag $t) => [$t->key => [
            'key' => $t->key, 'label' => $t->label, 'description' => $t->description,
        ]])->all();

        // Ručné štítky s názvom zo správy štítkov.
        foreach ($product->mailingLabels as $label) {
            $tags[$label->key] ??= ['key' => $label->key, 'label' => $label->name, 'description' => null, 'manual' => true, 'color' => $label->color];
        }

        foreach ($this->contactTags($product) as $key) {
            $tags[$key] ??= ['key' => $key, 'label' => $key, 'description' => null];
        }

        return array_values($tags);
    }

    /** @return list<string> */
    private function contactTags(Product $product): array
    {
        $decode = fn ($tags) => is_array($tags) ? $tags : (json_decode((string) $tags, true) ?: []);
        $query = fn (string $column) => MailingContact::query()->where('product_id', $product->id)
            ->whereNotNull($column)->distinct()->pluck($column)->flatMap($decode);

        return $query('tags')->merge($query('manual_tags'))->unique()->sort()->values()->all();
    }

    /** @return list<string> */
    private function contactVariables(Product $product): array
    {
        return MailingContact::query()->where('product_id', $product->id)->whereNotNull('attributes')
            ->latest('updated_at')->limit(50)->pluck('attributes')
            ->flatMap(fn ($attributes) => array_keys((array) $attributes))
            ->unique()->sort()->values()->all();
    }

    /** @return Collection<int, Product> */
    private function products()
    {
        return Product::query()->orderBy('name')->get(['key', 'name']);
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        return [
            'enabled' => $this->sender->enabled(),
            'dry_run' => $this->sender->dryRun(),
            'hourly_limit' => (int) config('accounts.mailing.hourly_limit'),
            'timezone' => config('app.timezone'),
        ];
    }
}
