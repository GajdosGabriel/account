<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ručná výnimka nad rámec plánu – napríklad dočasne zvýšený limit
 * alebo beta funkcia pre konkrétneho zákazníka.
 *
 * Hodnota je zabalená ako {"value": …}, aby JSON stĺpec uniesol aj null
 * (null = neobmedzene).
 */
class EntitlementOverride extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id', 'product_id', 'feature', 'value', 'expires_at', 'note', 'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function rawValue(): mixed
    {
        return $this->value['value'] ?? null;
    }

    /** @return array<string, mixed> */
    public static function wrap(mixed $value): array
    {
        return ['value' => $value];
    }
}
