<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Doklad – faktúra, zálohová faktúra alebo dobropis.
 *
 * Dve pravidlá, ktoré držia celý model pokope:
 *
 *  1. Koncept sa smie meniť ľubovoľne, vystavený doklad už nikdy.
 *     Oprava = dobropis. Vynucuje InvoicePolicy.
 *  2. Fakturačné údaje sa v okamihu vystavenia odfotia do snapshotov.
 *     Keď si zákazník o rok zmení sídlo, stará faktúra sa nezmení.
 */
class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id', 'subscription_id', 'number', 'type', 'status',
        'number_series_id', 'sequence', 'parent_invoice_id',
        'subtotal_cents', 'discount_cents', 'vat_cents', 'total_cents',
        'rounding_cents', 'paid_cents', 'vat_rate', 'vat_summary', 'reverse_charge',
        'currency', 'variable_symbol', 'constant_symbol', 'specific_symbol', 'payment_method',
        'billing_snapshot', 'supplier_snapshot', 'locale',
        'note', 'internal_note', 'vat_note',
        'issued_at', 'delivered_at', 'due_at', 'paid_at',
        'sent_at', 'sent_count', 'sent_to', 'last_reminder_at', 'reminder_count', 'cancelled_at',
        'external_id', 'pdf_url', 'pdf_path', 'exported_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'status' => InvoiceStatus::class,
            'payment_method' => PaymentMethod::class,
            'billing_snapshot' => 'array',
            'supplier_snapshot' => 'array',
            'vat_summary' => 'array',
            'reverse_charge' => 'boolean',
            'vat_rate' => 'decimal:2',
            'issued_at' => 'date',
            'delivered_at' => 'date',
            'due_at' => 'date',
            'paid_at' => 'datetime',
            'sent_at' => 'datetime',
            'last_reminder_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'exported_at' => 'datetime',
        ];
    }

    /* ---------------------------------------------------------------
     | Vzťahy
     |---------------------------------------------------------------*/

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<InvoiceEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(InvoiceEvent::class)->latest('created_at')->latest('id');
    }

    /** @return BelongsTo<InvoiceNumberSeries, $this> */
    public function numberSeries(): BelongsTo
    {
        return $this->belongsTo(InvoiceNumberSeries::class, 'number_series_id');
    }

    /** Pôvodný doklad, ku ktorému je tento dobropis / vyúčtovanie zálohy. */
    /** @return BelongsTo<Invoice, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'parent_invoice_id');
    }

    /** @return HasMany<Invoice, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Invoice::class, 'parent_invoice_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ---------------------------------------------------------------
     | Stav
     |---------------------------------------------------------------*/

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::Paid;
    }

    public function isCancelled(): bool
    {
        return $this->status === InvoiceStatus::Cancelled;
    }

    public function isCreditNote(): bool
    {
        return $this->type === InvoiceType::CreditNote;
    }

    /** Po splatnosti a stále nezaplatené. */
    public function isOverdue(): bool
    {
        return $this->status->isOpen()
            && $this->due_at !== null
            && $this->due_at->isPast();
    }

    public function daysOverdue(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return (int) $this->due_at->startOfDay()->diffInDays(Carbon::today());
    }

    /** Koľko ešte chýba do úplnej úhrady (v centoch). */
    public function outstandingCents(): int
    {
        return max(0, $this->total_cents - $this->paid_cents);
    }

    /* ---------------------------------------------------------------
     | Sumy
     |---------------------------------------------------------------*/

    public function subtotal(): float
    {
        return $this->subtotal_cents / 100;
    }

    public function vat(): float
    {
        return $this->vat_cents / 100;
    }

    public function total(): float
    {
        return $this->total_cents / 100;
    }

    public function outstanding(): float
    {
        return $this->outstandingCents() / 100;
    }

    /** "1 234,50 €" – slovenský formát s medzerou ako oddeľovačom tisícov. */
    public function formatMoney(?int $cents = null): string
    {
        $cents ??= $this->total_cents;

        return number_format($cents / 100, 2, ',', "\u{00A0}")."\u{00A0}".$this->currency;
    }

    /**
     * Prepočíta hlavičku zo súčtu položiek vrátane rekapitulácie DPH.
     * Volá sa vždy po zmene položiek konceptu.
     */
    public function recalculate(): static
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        $summary = [];
        $subtotal = 0;
        $vat = 0;

        foreach ($items as $item) {
            $rate = number_format((float) $item->vat_rate, 2, '.', '');

            $summary[$rate] ??= ['rate' => (float) $rate, 'base_cents' => 0, 'vat_cents' => 0];
            $summary[$rate]['base_cents'] += $item->subtotal_cents;
            $summary[$rate]['vat_cents'] += $item->vat_cents;

            $subtotal += $item->subtotal_cents;
            $vat += $item->vat_cents;
        }

        krsort($summary);

        $this->subtotal_cents = $subtotal;
        $this->vat_cents = $vat;
        $this->total_cents = $subtotal + $vat + $this->rounding_cents;
        $this->vat_summary = array_values($summary);

        // Hlavná sadzba = tá s najväčším základom; ide len o zobrazenie.
        $this->vat_rate = collect($summary)->sortByDesc('base_cents')->first()['rate'] ?? 0;

        return $this;
    }

    /* ---------------------------------------------------------------
     | História
     |---------------------------------------------------------------*/

    /** @param array<string, mixed> $meta */
    public function recordEvent(string $event, ?string $description = null, array $meta = []): InvoiceEvent
    {
        return $this->events()->create([
            'user_id' => auth()->id(),
            'event' => $event,
            'description' => $description,
            'meta' => $meta ?: null,
            'created_at' => now(),
        ]);
    }

    /* ---------------------------------------------------------------
     | Doručenie
     |---------------------------------------------------------------*/

    /** Kam poslať faktúru – fakturačný e-mail, kontakt typu billing, inak hlavný e-mail. */
    public function recipientEmail(): ?string
    {
        $snapshot = $this->billing_snapshot['email'] ?? null;

        if (filled($snapshot)) {
            return $snapshot;
        }

        $organization = $this->organization;

        return $organization?->billing_email
            ?: $organization?->contacts->firstWhere('type', 'billing')?->email
            ?: $organization?->email;
    }

    public function filename(): string
    {
        $prefix = match ($this->type) {
            InvoiceType::Proforma => 'zalohova-faktura',
            InvoiceType::CreditNote => 'dobropis',
            default => 'faktura',
        };

        return $prefix.'-'.str_replace(['/', ' '], '-', (string) $this->number).'.pdf';
    }

    /* ---------------------------------------------------------------
     | Scopes
     |---------------------------------------------------------------*/

    /** @param Builder<Invoice> $query */
    public function scopeOfType(Builder $query, InvoiceType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    /** @param Builder<Invoice> $query */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereIn('status', [
            InvoiceStatus::Issued->value,
            InvoiceStatus::Sent->value,
            InvoiceStatus::PartiallyPaid->value,
            InvoiceStatus::Overdue->value,
        ]);
    }

    /** @param Builder<Invoice> $query */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->unpaid()->whereDate('due_at', '<', today());
    }

    /** @param Builder<Invoice> $query */
    public function scopeIssuedBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('issued_at', [$from, $to]);
    }
}
