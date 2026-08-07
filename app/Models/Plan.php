<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\PlanFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id', 'key', 'name', 'price_cents', 'currency', 'interval',
        'trial_days', 'features', 'is_active', 'sort_order', 'external_price_id',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function price(): float
    {
        return $this->price_cents / 100;
    }

    public function formattedPrice(): string
    {
        return number_format($this->price(), 2, ',', ' ').' '.$this->currency;
    }
}
