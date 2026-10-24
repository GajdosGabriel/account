<?php

namespace App\Http\Controllers\Api;

use App\Models\MailingCampaign;
use App\Models\MailingContact;
use App\Models\MailingDelivery;
use App\Models\MailingTag;
use App\Models\MailingTrigger;
use App\Models\MailSuppression;
use App\Models\Organization;
use App\Services\Mailing\MailingEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Hromadný emailing pre projekty.
 *
 * Kampane, šablóny a odosielanie sú v Accounte. Projekt hlási udalosti
 * (s premennými do textu), ruší ich pri storne veci, priebežne posiela svoj
 * adresár (štítky, atribúty, súhlas) a zoznam udalostí a štítkov, ktoré pozná.
 */
class MailingApiController extends ApiController
{
    /** Kľúč udalosti alebo štítku. */
    private const KEY = '/^[a-z0-9_.\-]+$/i';

    public function __construct(private readonly MailingEvents $events) {}

    /** Nastala udalosť – aktívne automatizácie zaradia doručenia. */
    public function event(Request $request): JsonResponse
    {
        $product = $this->product($request);

        $data = $request->validate([
            'event' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_.\-]+$/i'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'ref' => ['nullable', 'array'],
            'ref.type' => ['required_with:ref', 'string', 'max:60', 'regex:/^[a-z0-9_.\-]+$/i'],
            'ref.id' => ['required_with:ref', 'max:120'],
            'ref.label' => ['nullable', 'string', 'max:255'],
            'ref.url' => ['nullable', 'url', 'max:500'],
            'dedupe_key' => ['nullable', 'string', 'max:191'],
            'occurred_at' => ['nullable', 'date'],
            'variables' => ['nullable', 'array', 'max:50'],
            'variables.*' => ['nullable', function (string $attribute, mixed $value, \Closure $fail) {
                if (! is_scalar($value) || mb_strlen((string) $value) > 2000) {
                    $fail(__('validation.string', ['attribute' => $attribute]));
                }
            }],
            'thread_id' => ['nullable', 'string', 'max:255', 'regex:/^<?[^\s<>@]+@[^\s<>@]+>?$/'],
        ]);

        $ref = isset($data['ref']) ? [...$data['ref'], 'id' => (string) $data['ref']['id']] : null;

        $queued = $this->events->record(
            $product,
            $data['event'],
            $data['email'],
            $data['name'] ?? null,
            $ref,
            // Bez kľúča je udalosť jedna na vec (alebo jedna na adresu, ak vec chýba).
            $data['dedupe_key'] ?? ($ref ? $ref['type'].':'.$ref['id'] : ''),
            isset($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : null,
            $data['variables'] ?? [],
            $data['thread_id'] ?? null,
        );

        return response()->json(['data' => ['queued' => $queued]], 202);
    }

    /** Vec sa zrušila – čakajúce maily k nej sa neodošlú. */
    public function cancel(Request $request): JsonResponse
    {
        $product = $this->product($request);

        $data = $request->validate([
            'ref' => ['required', 'array'],
            'ref.type' => ['required', 'string', 'max:60'],
            'ref.id' => ['required', 'max:120'],
            'except' => ['nullable', 'array'],
            'except.*' => ['string', 'max:60'],
        ]);

        $skipped = $this->events->cancel($product, $data['ref']['type'], (string) $data['ref']['id'], $data['except'] ?? []);

        return response()->json(['data' => ['skipped' => $skipped]]);
    }

    /**
     * Zoznam udalostí, ktoré projekt hlási. Celý zoznam nahradí doterajší;
     * kampane na odstránenú udalosť ostanú, len už nedostanú nové doručenia.
     */
    public function triggers(Request $request): JsonResponse
    {
        $product = $this->product($request);

        $data = $request->validate([
            'triggers' => ['present', 'array', 'max:100'],
            'triggers.*.key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_.\-]+$/i', 'distinct'],
            'triggers.*.label' => ['required', 'string', 'max:255'],
            'triggers.*.short_label' => ['nullable', 'string', 'max:80'],
            'triggers.*.variables' => ['nullable', 'array', 'max:50'],
            'triggers.*.variables.*' => ['string', 'max:60'],
            'triggers.*.quote_kind' => ['nullable', 'string', 'max:60'],
        ]);

        DB::transaction(function () use ($product, $data) {
            foreach ($data['triggers'] as $trigger) {
                MailingTrigger::updateOrCreate(
                    ['product_id' => $product->id, 'key' => $trigger['key']],
                    [
                        'label' => $trigger['label'],
                        'short_label' => $trigger['short_label'] ?? null,
                        'variables' => $trigger['variables'] ?? [],
                        'quote_kind' => $trigger['quote_kind'] ?? null,
                    ],
                );
            }

            MailingTrigger::query()->where('product_id', $product->id)
                ->whereNotIn('key', array_column($data['triggers'], 'key'))
                ->delete();
        });

        return response()->json(['data' => $product->mailingTriggers()->get(['key', 'label', 'short_label', 'variables', 'quote_kind'])]);
    }

    /**
     * Štítky kontaktov (segmenty), ktoré projekt posiela. Celý zoznam nahradí
     * doterajší; kontakty si odstránený štítok nechajú, kým ich projekt neprepíše.
     */
    public function tags(Request $request): JsonResponse
    {
        $product = $this->product($request);

        $data = $request->validate([
            'tags' => ['present', 'array', 'max:100'],
            'tags.*.key' => ['required', 'string', 'max:60', 'regex:'.self::KEY, 'distinct'],
            'tags.*.label' => ['required', 'string', 'max:255'],
            'tags.*.description' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($product, $data) {
            foreach ($data['tags'] as $tag) {
                MailingTag::updateOrCreate(
                    ['product_id' => $product->id, 'key' => $tag['key']],
                    ['label' => $tag['label'], 'description' => $tag['description'] ?? null],
                );
            }

            MailingTag::query()->where('product_id', $product->id)
                ->whereNotIn('key', array_column($data['tags'], 'key'))
                ->delete();
        });

        return response()->json(['data' => $product->mailingTags()->get(['key', 'label', 'description'])]);
    }

    /**
     * Adresár projektu: ľudia, ich štítky, atribúty do textu a súhlas.
     *
     * Štítky a atribúty projekt posiela celé – nahradia doterajšie. Súhlas
     * s ponukami sa raz udelí a cez API sa neodoberie (to je odhlásenie).
     * Odhlásenie z projektu prepíše stav len vtedy, keď je novšie než
     * posledná zmena v Accounte (napr. odhlásenie odkazom v maili).
     */
    public function contacts(Request $request): JsonResponse
    {
        $product = $this->product($request);

        $data = $request->validate([
            'contacts' => ['required', 'array', 'max:1000'],
            'contacts.*.email' => ['required', 'email:rfc', 'max:255'],
            'contacts.*.name' => ['nullable', 'string', 'max:255'],
            'contacts.*.permission_note' => ['nullable', 'string', 'min:5', 'max:255'],
            'contacts.*.permission_at' => ['nullable', 'date'],
            'contacts.*.organization_id' => ['nullable', 'uuid'],
            'contacts.*.tags' => ['nullable', 'array', 'max:20'],
            'contacts.*.tags.*' => ['string', 'max:60', 'regex:'.self::KEY],
            'contacts.*.attributes' => ['nullable', 'array', 'max:30'],
            'contacts.*.attributes.*' => ['nullable', function (string $attribute, mixed $value, \Closure $fail) {
                if (! is_scalar($value) || mb_strlen((string) $value) > 500) {
                    $fail(__('validation.string', ['attribute' => $attribute]));
                }
            }],
            'contacts.*.unsubscribed' => ['nullable', 'boolean'],
            'contacts.*.changed_at' => ['nullable', 'date'],
        ]);

        $added = 0;
        $updated = 0;

        foreach ($data['contacts'] as $row) {
            $organization = isset($row['organization_id'])
                ? Organization::where('uuid', $row['organization_id'])->first()
                : null;

            $contact = MailingContact::firstOrNew(['product_id' => $product->id, 'email' => strtolower(trim($row['email']))]);
            $new = ! $contact->exists;

            $contact->fill(array_filter([
                'name' => $row['name'] ?? null,
                'organization_id' => $organization?->isLinkedTo($product) ? $organization->id : null,
            ], fn ($v) => $v !== null));

            if (array_key_exists('tags', $row)) {
                $tags = array_values(array_unique($row['tags'] ?? []));
                sort($tags);
                $contact->tags = $tags ?: null;
            }

            if (array_key_exists('attributes', $row)) {
                $attributes = array_filter($row['attributes'] ?? [], fn ($v, $k) => $v !== null && preg_match('/^[a-z0-9_]+$/i', (string) $k), ARRAY_FILTER_USE_BOTH);
                $contact->attributes = $attributes ?: null;
            }

            if (! $contact->hasConsent() && filled($row['permission_note'] ?? null)) {
                $contact->permission_note = $row['permission_note'];
                $contact->permission_at = isset($row['permission_at']) ? Carbon::parse($row['permission_at']) : now();
            }

            if (isset($row['unsubscribed'])) {
                $changedAt = isset($row['changed_at']) ? Carbon::parse($row['changed_at']) : now();

                if ($row['unsubscribed'] !== (bool) $contact->unsubscribed_at
                    && (! $contact->unsubscribed_changed_at || $changedAt->gt($contact->unsubscribed_changed_at))) {
                    $contact->unsubscribed_at = $row['unsubscribed'] ? $changedAt : null;
                    $contact->unsubscribed_changed_at = $changedAt;
                }
            }

            if ($contact->isDirty()) {
                $unsubscribed = $contact->isDirty('unsubscribed_at') && $contact->unsubscribed_at;
                $contact->save();
                $new ? $added++ : $updated++;

                if ($unsubscribed) {
                    $this->skipPending($contact, __('mailing.errors.unsubscribed'), offersOnly: true);
                }
            }
        }

        return response()->json(['data' => ['added' => $added, 'updated' => $updated]], 201);
    }

    /**
     * Projekt zmazal človeka (účet, skúšku) – kontakt zmizne aj s čakajúcimi
     * mailmi. Odoslané doručenia ostanú v štatistikách bez väzby na kontakt.
     */
    public function remove(Request $request): JsonResponse
    {
        $product = $this->product($request);

        $data = $request->validate([
            'emails' => ['required', 'array', 'max:1000'],
            'emails.*' => ['required', 'string', 'max:255'],
        ]);

        $removed = 0;

        MailingContact::query()->where('product_id', $product->id)
            ->whereIn('email', array_map(fn ($e) => strtolower(trim($e)), $data['emails']))
            ->get()
            ->each(function (MailingContact $contact) use (&$removed) {
                $this->skipPending($contact, __('mailing.errors.unreachable'));
                $contact->delete();
                $removed++;
            });

        return response()->json(['data' => ['removed' => $removed]]);
    }

    private function skipPending(MailingContact $contact, string $reason, bool $offersOnly = false): void
    {
        MailingDelivery::query()->where('contact_id', $contact->id)->where('status', MailingDelivery::PENDING)
            ->when($offersOnly, fn ($q) => $q->whereHas('campaign', fn ($c) => $c->where('kind', '!=', MailingCampaign::SERVICE)))
            ->update(['status' => MailingDelivery::SKIPPED, 'error' => $reason, 'updated_at' => now()]);
    }

    public function contact(Request $request, string $email): JsonResponse
    {
        $product = $this->product($request);
        $email = strtolower(trim($email));

        $contact = MailingContact::query()->where('product_id', $product->id)->where('email', $email)->first();
        $suppression = MailSuppression::query()->where('product_id', $product->id)->where('email', $email)->first();

        return response()->json(['data' => [
            'email' => $email,
            'exists' => $contact !== null,
            'name' => $contact?->name,
            'tags' => $contact?->tags ?? [],
            'permission_note' => $contact?->permission_note,
            'permission_at' => $contact?->permission_at?->toIso8601String(),
            'unsubscribed_at' => $contact?->unsubscribed_at?->toIso8601String(),
            'bounced_at' => $contact?->bounced_at?->toIso8601String(),
            'suppressed' => $suppression !== null,
            'suppression_reason' => $suppression?->reason,
            'reachable' => $contact?->isReachable() ?? false,
        ]]);
    }
}
