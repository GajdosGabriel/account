<?php

namespace App\Models;

use App\Enums\AddressType;
use App\Enums\LegalForm;
use App\Enums\SubjectType;
use App\Enums\VatMode;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // identifikácia
        'name', 'legal_name', 'legal_form', 'subject_type',
        'ico', 'dic', 'ic_dph', 'vat_mode', 'oss_registered',
        // zápis v registri
        'register_court', 'register_section', 'register_insert', 'established_at',
        // sídlo
        'street', 'street_no', 'city', 'postal_code', 'region', 'country',
        // kontakt
        'email', 'billing_email', 'phone', 'website',
        // overenie fakturačného e-mailu píše service, nie formulár –
        // preto tu `billing_email_verified_*` zámerne nie je
        // banka
        'bank_name', 'iban', 'swift',
        // fakturačné preferencie
        'currency', 'payment_terms_days', 'payment_method',
        'invoice_language', 'invoice_delivery', 'supplier_number',
        // interné
        'status', 'note', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'legal_form' => LegalForm::class,
            'subject_type' => SubjectType::class,
            'vat_mode' => VatMode::class,
            'oss_registered' => 'boolean',
            'established_at' => 'date',
            'payment_terms_days' => 'integer',
            'ico_verified_at' => 'datetime',
            'vat_verified_at' => 'datetime',
            'billing_email_verified_at' => 'datetime',
            'billing_email_verification_sent_at' => 'datetime',
            'registry_snapshot' => 'array',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $organization) {
            $organization->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /* ---------------------------------------------------------------
     | Vzťahy
     |---------------------------------------------------------------*/

    /** @return HasMany<OrganizationAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(OrganizationAddress::class)->orderByDesc('is_default');
    }

    /** @return HasMany<OrganizationContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(OrganizationContact::class)->orderByDesc('is_primary');
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot(['external_ref', 'linked_at'])
            ->withTimestamps();
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<EntitlementOverride, $this> */
    public function entitlementOverrides(): HasMany
    {
        return $this->hasMany(EntitlementOverride::class);
    }

    /** @return HasMany<UsageReport, $this> */
    public function usageReports(): HasMany
    {
        return $this->hasMany(UsageReport::class);
    }

    /* ---------------------------------------------------------------
     | Adresy
     |---------------------------------------------------------------*/

    /** Sídlo / miesto podnikania – ako je na živnostenskom liste alebo vo výpise. */
    /** @return array<string, mixed> */
    public function registeredAddress(): array
    {
        return [
            'street' => trim($this->street.' '.($this->street_no ?? '')),
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'region' => $this->region,
            'country' => $this->country,
            'line' => $this->addressLine(),
        ];
    }

    public function addressLine(): string
    {
        return collect([
            trim($this->street.' '.($this->street_no ?? '')),
            trim(($this->postal_code ?? '').' '.($this->city ?? '')),
            $this->country,
        ])->filter(fn ($part) => filled(trim((string) $part)))->implode(', ');
    }

    /** Predvolená adresa daného typu, s návratom na sídlo. */
    public function addressOfType(AddressType $type): ?OrganizationAddress
    {
        return $this->addresses->firstWhere(fn (OrganizationAddress $a) => $a->type === $type && $a->is_default)
            ?? $this->addresses->firstWhere(fn (OrganizationAddress $a) => $a->type === $type);
    }

    /**
     * Kam posielať papierovú poštu.
     * Ak firma nemá zvlášť poštovú adresu, použije sa sídlo.
     *
     * @return array<int, string>
     */
    public function mailingLines(): array
    {
        $mailing = $this->addressOfType(AddressType::Mailing);

        if ($mailing) {
            return $mailing->envelopeLines();
        }

        return array_values(array_filter([
            $this->legal_name ?: $this->name,
            trim($this->street.' '.($this->street_no ?? '')),
            trim(($this->postal_code ?? '').' '.($this->city ?? '')),
            $this->country !== 'SK' ? $this->country : null,
        ]));
    }

    /* ---------------------------------------------------------------
     | Fakturácia
     |---------------------------------------------------------------*/

    /** Riadok o zápise v registri – povinný údaj na faktúre. */
    public function registrationLine(): ?string
    {
        if (blank($this->register_court)) {
            return null;
        }

        return collect([
            $this->register_court,
            $this->register_section ? 'oddiel '.$this->register_section : null,
            $this->register_insert ? 'vložka č. '.$this->register_insert : null,
        ])->filter()->implode(', ');
    }

    public function isVatPayer(): bool
    {
        return $this->vat_mode?->isPayer() ?? false;
    }

    /** Súkromná osoba – občan bez IČO, nie podnikateľ. */
    public function isPerson(): bool
    {
        return $this->subject_type?->isPerson() ?? false;
    }

    /* ---------------------------------------------------------------
     | Fakturačný e-mail
     |---------------------------------------------------------------*/

    /**
     * Adresa, na ktorú reálne chodia faktúry.
     *
     * Zámerne bez kontaktov typu `billing` – tie sa dajú pridať a zmazať
     * nezávisle a overenie by sa pri každej zmene kontaktu rozsypalo.
     * Overuje sa to, čo je na firme.
     */
    public function billingEmail(): ?string
    {
        return $this->billing_email ?: $this->email;
    }

    /**
     * Overenie platí len pre tú adresu, ktorá sa overila.
     *
     * Preto tu nie je „pri zmene zruš overenie“ – po prepísaní e-mailu
     * prestane sedieť porovnanie a firma je automaticky neoverená.
     * Funguje to aj vtedy, keď stĺpec zmení iná cesta než formulár.
     */
    public function hasVerifiedBillingEmail(): bool
    {
        $email = $this->billingEmail();

        return filled($email)
            && $this->billing_email_verified_at !== null
            && $this->emailsMatch($this->billing_email_verified_address, $email);
    }

    public function markBillingEmailVerified(string $email): void
    {
        $this->forceFill([
            'billing_email_verified_address' => $email,
            'billing_email_verified_at' => now(),
        ])->save();
    }

    /** Adresy sa porovnávajú bez ohľadu na veľkosť písmen – `Firma@` a `firma@` je tá istá schránka. */
    public function emailsMatch(?string $a, ?string $b): bool
    {
        return filled($a) && filled($b) && mb_strtolower(trim($a)) === mb_strtolower(trim($b));
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Kópia fakturačných údajov v čase vystavenia faktúry.
     * Faktúra sa nesmie spätne meniť pri edite firmy.
     *
     * @return array<string, mixed>
     */
    public function billingSnapshot(): array
    {
        return [
            'name' => $this->legal_name ?: $this->name,
            'legal_form' => $this->legal_form?->shortLabel(),
            'ico' => $this->ico,
            'dic' => $this->dic,
            'ic_dph' => $this->ic_dph,
            'vat_mode' => $this->vat_mode?->value,
            'registration' => $this->registrationLine(),
            'address' => $this->registeredAddress(),
            'mailing' => $this->mailingLines(),
            'email' => $this->billing_email ?: $this->email,
            'iban' => $this->iban,
            'swift' => $this->swift,
            'bank_name' => $this->bank_name,
            'payment_terms_days' => $this->payment_terms_days,
            'currency' => $this->currency,
        ];
    }

    /* ---------------------------------------------------------------
     | Projekty
     |---------------------------------------------------------------*/

    public function subscriptionFor(Product $product): ?Subscription
    {
        return $this->subscriptions()->where('product_id', $product->id)->first();
    }

    public function isLinkedTo(Product $product): bool
    {
        return $this->products()->whereKey($product->id)->exists();
    }

    public function linkTo(Product $product, ?string $externalRef = null): void
    {
        $this->products()->syncWithoutDetaching([
            $product->id => ['external_ref' => $externalRef, 'linked_at' => now()],
        ]);
    }

    /** @param Builder<Organization> $query */
    public function scopeForProduct(Builder $query, Product $product): Builder
    {
        return $query->whereHas('products', fn ($q) => $q->whereKey($product->id));
    }

    /** Chýbajúce údaje, bez ktorých sa nedá vystaviť faktúra. */
    /** @return array<int, string> */
    public function missingBillingFields(): array
    {
        $missing = [];

        // Od občana sa IČO pýtať nedá – nikdy ho mať nebude. Bez tejto
        // výnimky by mu doklad navždy hlásil chýbajúci údaj a nedal sa
        // vystaviť, hoci na faktúru pre súkromnú osobu stačí meno a adresa.
        if (blank($this->ico) && ! $this->isPerson()) {
            $missing[] = 'IČO';
        }

        if (blank($this->street) || blank($this->city) || blank($this->postal_code)) {
            $missing[] = $this->isPerson() ? 'adresa' : 'sídlo';
        }

        if (blank($this->billing_email) && blank($this->email)) {
            $missing[] = 'e-mail na faktúry';
        }

        if ($this->vat_mode?->hasVatNumber() && blank($this->ic_dph)) {
            $missing[] = 'IČ DPH';
        }

        return $missing;
    }
}
