<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['key', 'name', 'url', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /** Katalóg funkcií – čo vôbec môže tento projekt merať a obmedzovať. */
    /** @return HasMany<ProductFeature, $this> */
    public function features(): HasMany
    {
        return $this->hasMany(ProductFeature::class)->orderBy('sort_order')->orderBy('key');
    }

    /** @return HasMany<Plan, $this> */
    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class)->orderBy('sort_order')->orderBy('price_cents');
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @return BelongsToMany<Organization, $this> */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot(['external_ref', 'linked_at'])
            ->withTimestamps();
    }

    /** @return HasMany<ServiceClient, $this> */
    public function serviceClients(): HasMany
    {
        return $this->hasMany(ServiceClient::class);
    }

    /** @return HasMany<WebhookEndpoint, $this> */
    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class);
    }

    /** @return HasMany<UsageReport, $this> */
    public function usageReports(): HasMany
    {
        return $this->hasMany(UsageReport::class);
    }

    public function defaultPlan(): ?Plan
    {
        return $this->plans()->where('is_active', true)->first();
    }
}
