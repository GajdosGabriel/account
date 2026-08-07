<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageReport extends Model
{
    protected $fillable = ['organization_id', 'product_id', 'metric', 'value', 'reported_at'];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'reported_at' => 'datetime',
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

    /** Údaj starší ako 48 hodín považujeme za neaktuálny. */
    public function isStale(): bool
    {
        return $this->reported_at->lt(now()->subHours(48));
    }
}
