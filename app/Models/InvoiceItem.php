<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Jedna položka faktúry.
 *
 * `unit_price` je v stotinách centa (1 € = 10 000), aby sa dali fakturovať
 * aj drobné jednotkové ceny bez straty presnosti. Všetky ostatné sumy
 * sú v celých centoch a počítajú sa tu, nie vo view.
 */
class InvoiceItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_id', 'plan_id', 'product_id',
        'description', 'detail',
        'quantity', 'unit', 'unit_price', 'discount_percent', 'vat_rate',
        'subtotal_cents', 'vat_cents', 'total_cents',
        'period_start', 'period_end', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'discount_percent' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $item) => $item->recalculate());
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Prepočet položky.
     *
     * Zaokrúhľuje sa až na úrovni riadku (matematicky, na cent) – tak to
     * robia aj Pohoda, SuperFaktúra či Kros. Súčet riadkov potom vždy sedí
     * so súčtom faktúry a nevznikajú haliere navyše.
     */
    public function recalculate(): void
    {
        $gross = (float) $this->quantity * (float) $this->unit_price;   // stotiny centa
        $afterDiscount = $gross * (1 - ((float) $this->discount_percent / 100));

        $this->subtotal_cents = (int) round($afterDiscount / 100);
        $this->vat_cents = (int) round($this->subtotal_cents * ((float) $this->vat_rate / 100));
        $this->total_cents = $this->subtotal_cents + $this->vat_cents;
    }

    /** Jednotková cena v eurách – na zobrazenie. */
    public function unitPrice(): float
    {
        return $this->unit_price / 10000;
    }

    /** "1. 2. – 28. 2. 2026" alebo null. */
    public function periodLabel(): ?string
    {
        if (! $this->period_start || ! $this->period_end) {
            return null;
        }

        return $this->period_start->format('j. n. Y').' – '.$this->period_end->format('j. n. Y');
    }
}
