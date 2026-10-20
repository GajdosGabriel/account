<?php

namespace App\Http\Controllers\Api;

use App\Models\MailingContact;
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
 * (s premennými do textu), ruší ich pri storne veci, posiela kontakty so
 * súhlasom a zoznam udalostí, ktoré vie hlásiť.
 */
class MailingApiController extends ApiController
{
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
     * Kontakty so súhlasom so zasielaním ponúk. Existujúci kontakt sa
     * nezmaže ani „neprihlási“ späť – odhlásenie platí, kým ho nezruší človek.
     */
    public function contacts(Request $request): JsonResponse
    {
        $product = $this->product($request);

        $data = $request->validate([
            'contacts' => ['required', 'array', 'max:1000'],
            'contacts.*.email' => ['required', 'email:rfc', 'max:255'],
            'contacts.*.name' => ['nullable', 'string', 'max:255'],
            'contacts.*.permission_note' => ['required', 'string', 'min:5', 'max:255'],
            'contacts.*.permission_at' => ['nullable', 'date'],
            'contacts.*.organization_id' => ['nullable', 'uuid'],
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

            if ($new) {
                $contact->permission_note = $row['permission_note'];
                $contact->permission_at = isset($row['permission_at']) ? Carbon::parse($row['permission_at']) : now();
            }

            if ($contact->isDirty()) {
                $contact->save();
                $new ? $added++ : $updated++;
            }
        }

        return response()->json(['data' => ['added' => $added, 'updated' => $updated]], 201);
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
