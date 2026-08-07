<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WebhookEndpoint extends Model
{
    use SoftDeletes;

    protected $fillable = ['product_id', 'url', 'secret', 'events', 'is_active'];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $endpoint) {
            $endpoint->secret ??= 'whsec_'.Str::random(48);
        });
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<WebhookDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class)->latest();
    }

    public function listensTo(string $event): bool
    {
        $events = $this->events ?? [];

        return $events === [] || in_array('*', $events, true) || in_array($event, $events, true);
    }
}
