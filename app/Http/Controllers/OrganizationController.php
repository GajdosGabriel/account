<?php

namespace App\Http\Controllers;

use App\Enums\AddressType;
use App\Enums\LegalForm;
use App\Enums\SubjectType;
use App\Enums\VatMode;
use App\Http\Requests\OrganizationRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\AuditLog;
use App\Models\EntitlementOverride;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Product;
use App\Repositories\OrganizationQuery;
use App\Services\Billing\BillingEmailVerifier;
use App\Services\Billing\SubscriptionManager;
use App\Services\Entitlements\EntitlementService;
use App\Support\Abilities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $query = OrganizationQuery::fromRequest($request);

        // „status=trashed“ nie je stav firmy, ale prepnutie zoznamu na kôš.
        // Rieši sa tu a nie v OrganizationQuery, ktorý používa aj API –
        // projekt sa cez token k zmazaným firmám dostať nemá.
        $trashed = $request->query('status') === 'trashed';

        $organizations = $query->builder()
            ->when($trashed, fn ($q) => $q->onlyTrashed())
            ->paginate(OrganizationQuery::PER_PAGE)
            ->withQueryString()
            ->through(fn (Organization $org) => [
                'id' => $org->uuid,
                'name' => $org->name,
                'ico' => $org->ico,
                'ic_dph' => $org->ic_dph,
                'city' => $org->city,
                'legal_form' => $org->legal_form?->shortLabel(),
                'status' => $org->status,
                'products_count' => $org->products_count,
                'verified' => $org->ico_verified_at !== null,
                ...Abilities::payload($org),
            ]);

        return Inertia::render('Organizations/Index', [
            'organizations' => $organizations,
            // OrganizationQuery neznámy stav zahodí, do formulára ho preto
            // vraciame my – inak by sa výber sám prepol späť na „všetky“.
            'filters' => $trashed
                ? [...$query->toArray(), 'status' => 'trashed']
                : $query->toArray(),
            'trashed' => $trashed,
            'trashed_count' => Organization::onlyTrashed()->count(),
            // Zoznam projektov do filtra. Neaktívne sa neskrývajú – ich firmy
            // v evidencii zostávajú a treba sa k nim vedieť dostať.
            'products' => Product::orderBy('name')->get(['key', 'name'])
                ->map(fn (Product $product) => ['key' => $product->key, 'name' => $product->name]),
            'statuses' => OrganizationQuery::STATUSES,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Organizations/Form', [
            'organization' => null,
            'legal_forms' => LegalForm::options(),
            'subject_types' => SubjectType::options(),
            'vat_modes' => VatMode::options(),
        ]);
    }

    public function store(OrganizationRequest $request, BillingEmailVerifier $verifier): RedirectResponse
    {
        $organization = Organization::create($request->organizationData());

        AuditLog::record('organization.created', $organization, [], $organization->id);

        $sent = $verifier->sendIfNeeded($organization);

        return redirect()->route('organizations.show', $organization)
            ->with('success', $sent
                ? 'Organizácia bola vytvorená. Na e-mail pre faktúry sme poslali žiadosť o potvrdenie.'
                : 'Organizácia bola vytvorená.');
    }

    public function show(Organization $organization, EntitlementService $entitlements): Response
    {
        // Adresy a kontakty sa načítavajú aj so zmazanými – v koši sa dajú
        // vrátiť späť a inak by po zmazaní z obrazovky ticho zmizli.
        $organization->load([
            'addresses' => fn ($q) => $q->withTrashed(),
            'contacts' => fn ($q) => $q->withTrashed(),
            'products', 'subscriptions.product', 'subscriptions.plan', 'usageReports.product',
        ]);

        $products = Product::with(['plans', 'features'])->get();

        return Inertia::render('Organizations/Show', [
            'organization' => [
                'id' => $organization->uuid,
                'name' => $organization->name,
                'legal_name' => $organization->legal_name,
                'legal_form' => $organization->legal_form?->shortLabel(),
                'subject_type' => $organization->subject_type?->value,
                'subject_type_label' => $organization->subject_type?->label(),
                'is_person' => $organization->isPerson(),
                'status' => $organization->status,
                'note' => $organization->note,

                'ico' => $organization->ico,
                'dic' => $organization->dic,
                'ic_dph' => $organization->ic_dph,
                'vat_mode' => $organization->vat_mode?->label(),
                'oss_registered' => $organization->oss_registered,
                'ico_verified_at' => $organization->ico_verified_at?->toDateTimeString(),
                'vat_verified_at' => $organization->vat_verified_at?->toDateTimeString(),

                'registration' => $organization->registrationLine(),
                'established_at' => $organization->established_at?->toDateString(),

                'address' => $organization->addressLine(),
                'mailing_lines' => $organization->mailingLines(),

                'email' => $organization->email,
                'billing_email' => $organization->billing_email,
                // Adresa, na ktorú doklady reálne odchádzajú, a jej stav.
                'billing_email_effective' => $organization->billingEmail(),
                'billing_email_verified' => $organization->hasVerifiedBillingEmail(),
                'billing_email_verified_at' => $organization->hasVerifiedBillingEmail()
                    ? $organization->billing_email_verified_at?->format('j. n. Y H:i')
                    : null,
                'billing_email_verification_sent_at' => $organization->billing_email_verification_sent_at?->format('j. n. Y H:i'),
                'phone' => $organization->phone,
                'website' => $organization->website,

                'bank_name' => $organization->bank_name,
                'iban' => $organization->iban,
                'swift' => $organization->swift,

                'currency' => $organization->currency,
                'payment_terms_days' => $organization->payment_terms_days,
                'invoice_delivery' => $organization->invoice_delivery,

                'missing_billing' => $organization->missingBillingFields(),
                ...Abilities::payload($organization),
            ],

            'addresses' => $organization->addresses->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type->value,
                'type_label' => $a->type->label(),
                'label' => $a->label,
                'recipient' => $a->recipient,
                'line' => $a->line(),
                'street' => $a->street,
                'street_no' => $a->street_no,
                'city' => $a->city,
                'postal_code' => $a->postal_code,
                'region' => $a->region,
                'country' => $a->country,
                'phone' => $a->phone,
                'note' => $a->note,
                'is_default' => $a->is_default,
                ...Abilities::payload($a),
            ]),

            'contacts' => $organization->contacts->map(fn ($c) => [
                'id' => $c->id,
                'type' => $c->type,
                'type_label' => $c->typeLabel(),
                'name' => $c->name,
                'position' => $c->position,
                'email' => $c->email,
                'phone' => $c->phone,
                'note' => $c->note,
                'is_primary' => $c->is_primary,
                ...Abilities::payload($c),
            ]),

            'address_types' => AddressType::options(),
            'contact_types' => [
                ['value' => 'general', 'label' => 'Všeobecný'],
                ['value' => 'billing', 'label' => 'Fakturácia'],
                ['value' => 'technical', 'label' => 'Technický'],
                ['value' => 'statutory', 'label' => 'Štatutár'],
            ],

            // Každý projekt zvlášť: naviazanie, plán, stav a spotreba
            'products' => $products->map(function (Product $product) use ($organization, $entitlements) {
                $linked = $organization->isLinkedTo($product);
                $subscription = $organization->subscriptionFor($product);
                $resolved = $linked ? $entitlements->for($organization, $product, fresh: true) : null;

                return [
                    'key' => $product->key,
                    'name' => $product->name,
                    'linked' => $linked,
                    'plans' => $product->plans->map(fn (Plan $plan) => [
                        'id' => $plan->id,
                        'key' => $plan->key,
                        'name' => $plan->name,
                        'price' => $plan->formattedPrice(),
                        'current' => $subscription?->plan_id === $plan->id,
                    ]),
                    'subscription' => $subscription ? [
                        'status' => $subscription->status->value,
                        'status_label' => $subscription->status->label(),
                        'plan' => $subscription->plan?->name,
                        'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                        'current_period_end' => $subscription->current_period_end?->toDateString(),
                        'grace_ends_at' => $subscription->grace_ends_at?->toDateString(),
                    ] : null,
                    'features' => $product->features->map(fn ($feature) => [
                        'key' => $feature->key,
                        'name' => $feature->name,
                        'type' => $feature->type,
                        'unit' => $feature->unit,
                        'metric' => $feature->metric,
                        'value' => $resolved['features'][$feature->key] ?? null,
                        'formatted' => $feature->formatValue($resolved['features'][$feature->key] ?? null),
                        'used' => $feature->metric ? ($resolved['usage'][$feature->metric] ?? null) : null,
                        'over' => isset($resolved['over_limit'][$feature->key]),
                    ]),
                ];
            }),

            'overrides' => $organization->entitlementOverrides()->withTrashed()->with('product')->get()
                ->map(fn (EntitlementOverride $o) => [
                    'id' => $o->id,
                    'product' => $o->product->name,
                    'product_key' => $o->product->key,
                    'feature' => $o->feature,
                    'value' => $o->rawValue(),
                    'expires_at' => $o->expires_at?->toDateString(),
                    'note' => $o->note,
                    ...Abilities::payload($o),
                ]),

            'invoices' => $organization->invoices()
                ->with('organization')
                ->orderByRaw('issued_at IS NULL DESC')
                ->latest('issued_at')
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(fn (Invoice $invoice) => (new InvoiceResource($invoice))->resolve()),

            // Zhrnutie pohľadávok voči tejto firme – to je to, čo chceš vidieť,
            // keď ti volá a pýta sa, či niečo dlhuje.
            'billing_summary' => [
                'total' => $organization->invoices()->count(),
                'outstanding_cents' => $outstanding = (int) $organization->invoices()
                    ->unpaid()->sum(DB::raw('total_cents - paid_cents')),
                'outstanding' => number_format($outstanding / 100, 2, ',', ' ').' '.($organization->currency ?: 'EUR'),
                'overdue_count' => $organization->invoices()->overdue()->count(),
            ],
        ]);
    }

    public function edit(Organization $organization): Response
    {
        return Inertia::render('Organizations/Form', [
            'organization' => array_merge(
                $organization->only([
                    'name', 'legal_name', 'ico', 'dic', 'ic_dph', 'oss_registered',
                    'register_court', 'register_section', 'register_insert',
                    'street', 'street_no', 'city', 'postal_code', 'region', 'country',
                    'email', 'billing_email', 'phone', 'website',
                    'bank_name', 'iban', 'swift',
                    'currency', 'payment_terms_days', 'payment_method',
                    'invoice_language', 'invoice_delivery', 'supplier_number',
                    'status', 'note',
                ]),
                [
                    'id' => $organization->uuid,
                    'subject_type' => $organization->subject_type?->value ?? SubjectType::Company->value,
                    'legal_form' => $organization->legal_form?->value,
                    'vat_mode' => $organization->vat_mode?->value,
                    'established_at' => $organization->established_at?->toDateString(),
                ],
            ),
            'legal_forms' => LegalForm::options(),
            'subject_types' => SubjectType::options(),
            'vat_modes' => VatMode::options(),
        ]);
    }

    public function update(OrganizationRequest $request, Organization $organization, BillingEmailVerifier $verifier): RedirectResponse
    {
        $organization->fill($request->organizationData());
        $changed = array_keys($organization->getDirty());
        $organization->save();

        AuditLog::record('organization.updated', $organization, ['changed' => $changed], $organization->id);

        // Po zmene adresy prestane sedieť overenie, takže sa žiadosť pošle
        // sama. Verifier si sám ustráži, či je vôbec čo posielať.
        $sent = $verifier->sendIfNeeded($organization);

        return redirect()->route('organizations.show', $organization)
            ->with('success', $sent
                ? 'Údaje boli uložené. Na nový e-mail pre faktúry sme poslali žiadosť o potvrdenie.'
                : 'Údaje boli uložené.');
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $this->authorize('delete', $organization);

        $organization->delete();

        AuditLog::record('organization.deleted', $organization, [], $organization->id);

        // Zmazaná firma je vo filtri „Kôš“ – tam aj skončíme, aby bolo
        // vidieť, kam sa podela a odkiaľ sa dá vrátiť.
        return redirect()->route('organizations.index', ['status' => 'trashed'])
            ->with('success', 'Organizácia bola presunutá do koša.');
    }

    /**
     * Nenávratné zmazanie – len firma bez dokladov.
     *
     * Vystavená faktúra musí v evidencii zostať aj po odchode zákazníka,
     * a to desať rokov. Firmu s dokladmi preto zmaže len kôš, nie databáza.
     */
    public function forceDelete(Organization $organization): RedirectResponse
    {
        $this->authorize('forceDelete', $organization);

        $name = $organization->name;
        $organization->forceDelete();

        AuditLog::record('organization.force_deleted', null, ['name' => $name]);

        return redirect()->route('organizations.index')
            ->with('success', __('actions.flash.force_deleted'));
    }

    /**
     * Vrátenie z koša.
     *
     * Firma sa maže mäkko práve preto, aby sa dala vrátiť – visia na nej
     * faktúry, predplatné aj história a tvrdé zmazanie by ich vzalo so sebou.
     */
    public function restore(string $organization): RedirectResponse
    {
        $organization = Organization::onlyTrashed()->where('uuid', $organization)->firstOrFail();

        $this->authorize('restore', $organization);

        $organization->restore();

        AuditLog::record('organization.restored', $organization, [], $organization->id);

        return redirect()->route('organizations.show', $organization)
            ->with('success', 'Organizácia bola obnovená.');
    }

    /* ---------------------------------------------------------------
     | Overenie fakturačného e-mailu
     |---------------------------------------------------------------*/

    /**
     * Kliknutie z overovacieho e-mailu.
     *
     * Stránka je verejná (chráni ju podpis v adrese), preto tu nesmie byť
     * nič z back-officu – zákazník má vidieť jednu vetu a nič viac.
     */
    public function verifyBillingEmail(Request $request, Organization $organization, BillingEmailVerifier $verifier): Response
    {
        $confirmed = $verifier->confirm($organization, (string) $request->query('hash'));

        return Inertia::render('Public/BillingEmailVerified', [
            'confirmed' => $confirmed,
            'organization' => $organization->name,
            // Adresu nezobrazujeme celú – stránku vie otvoriť ktokoľvek,
            // kto sa dostane k odkazu, a e-mail firmy mu do rúk nepatrí.
            'email' => $confirmed ? $this->maskEmail($organization->billingEmail()) : null,
        ]);
    }

    /** Ručné „poslať znova“ z detailu firmy. */
    public function resendBillingEmailVerification(Organization $organization, BillingEmailVerifier $verifier): RedirectResponse
    {
        if (blank($organization->billingEmail())) {
            return back()->with('error', 'Firma nemá vyplnený e-mail na faktúry.');
        }

        if ($organization->hasVerifiedBillingEmail()) {
            return back()->with('success', 'E-mail na faktúry je už overený.');
        }

        return $verifier->sendIfNeeded($organization, force: true)
            ? back()->with('success', 'Overovací e-mail bol odoslaný.')
            : back()->with('error', 'Overovací e-mail sa nepodarilo odoslať. Skús to o chvíľu znova.');
    }

    /** `f****a@firma.sk` – dosť na rozpoznanie, málo na zneužitie. */
    protected function maskEmail(?string $email): ?string
    {
        if (blank($email) || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);

        $visible = mb_substr($local, 0, 1).str_repeat('*', max(1, mb_strlen($local) - 2)).mb_substr($local, -1);

        return $visible.'@'.$domain;
    }

    /** Naviazanie alebo odviazanie firmy od projektu. */
    public function toggleProduct(Request $request, Organization $organization, Product $product): RedirectResponse
    {
        if ($organization->isLinkedTo($product)) {
            $organization->products()->detach($product->id);
            $message = "Odviazané od {$product->name}.";
        } else {
            $organization->linkTo($product);
            $message = "Naviazané na {$product->name}.";
        }

        app(EntitlementService::class)->flush($organization, $product);

        return back()->with('success', $message);
    }

    /** Nastavenie plánu pre konkrétny projekt. */
    public function subscribe(Request $request, Organization $organization, SubscriptionManager $manager): RedirectResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id']]);

        $plan = Plan::with('product')->findOrFail($data['plan_id']);
        $organization->linkTo($plan->product);

        $existing = $organization->subscriptionFor($plan->product);

        $existing
            ? $manager->changePlan($existing, $plan)
            : $manager->subscribe($organization, $plan);

        return back()->with('success', "Plán {$plan->name} je nastavený.");
    }

    public function cancelSubscription(Organization $organization, Product $product, SubscriptionManager $manager): RedirectResponse
    {
        $subscription = $organization->subscriptionFor($product);

        abort_unless($subscription !== null, 404);

        $manager->cancel($subscription, 'cancelled_by_operator');

        return back()->with('success', 'Predplatné bolo zrušené.');
    }

    public function activateSubscription(Organization $organization, Product $product, SubscriptionManager $manager): RedirectResponse
    {
        $subscription = $organization->subscriptionFor($product);

        abort_unless($subscription !== null, 404);

        $manager->activate($subscription, 'activated_by_operator');

        return back()->with('success', 'Predplatné je aktívne.');
    }

    /** Ručná výnimka nad rámec plánu. */
    public function storeOverride(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'product_key' => ['required', 'exists:products,key'],
            'feature' => ['required', 'string', 'max:60'],
            'value' => ['nullable'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $product = Product::where('key', $data['product_key'])->firstOrFail();
        $feature = $product->features()->where('key', $data['feature'])->firstOrFail();

        EntitlementOverride::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'product_id' => $product->id,
                'feature' => $feature->key,
            ],
            [
                'value' => EntitlementOverride::wrap($feature->castValue($data['value'] ?? null)),
                'expires_at' => $data['expires_at'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by_id' => $request->user()->id,
            ],
        );

        app(EntitlementService::class)->flush($organization, $product);

        return back()->with('success', 'Výnimka bola uložená.');
    }

    public function destroyOverride(Organization $organization, EntitlementOverride $override): RedirectResponse
    {
        $this->authorize('delete', $override);

        $product = $override->product;
        $override->delete();

        app(EntitlementService::class)->flush($organization, $product);

        return back()->with('success', __('actions.flash.deleted'));
    }

    public function restoreOverride(Organization $organization, EntitlementOverride $override): RedirectResponse
    {
        $this->authorize('restore', $override);

        $override->restore();

        app(EntitlementService::class)->flush($organization, $override->product);

        return back()->with('success', __('actions.flash.restored'));
    }

    public function forceDeleteOverride(Organization $organization, EntitlementOverride $override): RedirectResponse
    {
        $this->authorize('forceDelete', $override);

        $product = $override->product;
        $override->forceDelete();

        app(EntitlementService::class)->flush($organization, $product);

        return back()->with('success', __('actions.flash.force_deleted'));
    }
}
