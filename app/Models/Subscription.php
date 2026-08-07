<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id', 'product_id', 'plan_id', 'status', 'quantity',
        'trial_ends_at', 'current_period_start', 'current_period_end',
        'grace_ends_at', 'suspended_until', 'cancelled_at', 'ends_at',
        'external_id', 'external_customer_id', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'grace_ends_at' => 'datetime',
            'suspended_until' => 'datetime',
            'cancelled_at' => 'datetime',
            'ends_at' => 'datetime',
            'meta' => 'array',
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

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasMany<SubscriptionEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class)->latest('created_at');
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function grantsAccess(): bool
    {
        return $this->status->grantsAccess();
    }

    public function isReadOnly(): bool
    {
        return $this->status->isReadOnly();
    }

    public function onTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trialing
            && $this->trial_ends_at?->isFuture();
    }

    /** @param Builder<Subscription> $query */
    public function scopeStatus(Builder $query, SubscriptionStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }
}
