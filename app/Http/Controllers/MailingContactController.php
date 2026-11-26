<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SharesMailingData;
use App\Models\MailingContact;
use App\Models\MailingDelivery;
use App\Models\MailingLabel;
use App\Models\MailSuppression;
use App\Models\Product;
use App\Services\Mailing\BounceClassifier;
use App\Services\Mailing\ContactImportParser;
use App\Services\Mailing\MailingSender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Adresáti kampaní: kontakty všetkých projektov, import a export CSV,
 * hromadné akcie a ručné štítky.
 */
class MailingContactController extends Controller
{
    use SharesMailingData;

    public function __construct(private readonly MailingSender $sender) {}

    public function index(Request $request): Response
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
    public function store(Request $request): RedirectResponse
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
        [$rows, $skipped] = ContactImportParser::parse($data['emails']);

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
    public function export(Request $request): StreamedResponse
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

    /**
     * Oprava adresy a mena kontaktu. Kontakt si ponechá históriu doručení,
     * štítky, súhlas, odhlásenie aj token na odhlásenie. Nedoručiteľnosť
     * patrila starej adrese, preto sa pri zmene adresy zruší; blokovanie
     * starej adresy ostáva. Ešte neodoslané doručenia pôjdu na novú adresu.
     */
    public function update(Request $request, MailingContact $contact): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $email = strtolower(trim($data['email']));
        $changed = $email !== $contact->email;

        if ($changed) {
            $taken = MailingContact::withTrashed()->where('product_id', $contact->product_id)
                ->where('email', $email)->whereKeyNot($contact->id)->exists();

            if ($taken) {
                throw ValidationException::withMessages(['email' => __('mailing.contacts.email_taken')]);
            }

            if (MailSuppression::blocks($contact->product, $email)) {
                throw ValidationException::withMessages(['email' => __('mailing.contacts.email_suppressed')]);
            }
        }

        DB::transaction(function () use ($contact, $data, $email, $changed) {
            $contact->name = filled($data['name'] ?? null) ? trim($data['name']) : null;

            if ($changed) {
                $contact->forceFill(['email' => $email, 'bounced_at' => null, 'bounce_reason' => null, 'soft_bounces' => 0]);

                // Doručenie, ktoré kampaň už má pre novú adresu, sa nezdvojí.
                MailingDelivery::query()->where('contact_id', $contact->id)->where('status', MailingDelivery::PENDING)
                    ->each(function (MailingDelivery $delivery) use ($email) {
                        $duplicate = MailingDelivery::query()->where('campaign_id', $delivery->campaign_id)
                            ->where('dedupe_key', $delivery->dedupe_key)->where('email', $email)->exists();

                        $duplicate
                            ? $delivery->forceFill(['status' => MailingDelivery::SKIPPED, 'error' => __('mailing.contacts.email_changed_duplicate')])->save()
                            : $delivery->forceFill(['email' => $email])->save();
                    });
            }

            $contact->save();
        });

        return back()->with('success', __('mailing.contacts.updated'));
    }

    /** Ručné štítky kontaktu; štítky z projektu sa tu nemenia. */
    public function updateTags(Request $request, MailingContact $contact): RedirectResponse
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
    public function bulk(Request $request): RedirectResponse
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
                        'unsubscribe' => ! $contact->unsubscribed_at && $this->markUnsubscribed($contact),
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

    public function unsubscribe(MailingContact $contact): RedirectResponse
    {
        if (! $contact->unsubscribed_at) {
            $this->markUnsubscribed($contact);
        }

        return back()->with('success', __('mailing.contacts.unsubscribed'));
    }

    /**
     * Vylúčenú adresu vráti späť (adresa opravená, schránka uvoľnená).
     * Odhlásenie človeka sa tu nezruší – to musí urobiť on sám.
     */
    public function reactivate(MailingContact $contact): RedirectResponse
    {
        $contact->forceFill(['bounced_at' => null, 'bounce_reason' => null, 'soft_bounces' => 0])->save();

        MailSuppression::query()->where('product_id', $contact->product_id)->where('email', $contact->email)
            ->where('reason', MailSuppression::HARD_BOUNCE)->delete();

        return back()->with('success', __('mailing.contacts.reactivated'));
    }

    /** Ručné zmazanie je trvalé – na rozdiel od odstránenia projektom. */
    public function destroy(MailingContact $contact): RedirectResponse
    {
        $contact->forceDelete();

        return back()->with('success', __('mailing.contacts.deleted'));
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

    /* ---------------------------------------------------------------
     | Pomocné
     |---------------------------------------------------------------*/

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
            ->when($state === 'bounced', fn ($q) => $q->blocked());
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

    private function markUnsubscribed(MailingContact $contact): bool
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
}
