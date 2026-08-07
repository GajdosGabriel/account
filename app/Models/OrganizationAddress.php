<?php

namespace App\Models;

use App\Enums\AddressType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganizationAddress extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id', 'type', 'label', 'recipient',
        'street', 'street_no', 'city', 'postal_code', 'region', 'country',
        'phone', 'note', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'type' => AddressType::class,
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // V rámci jedného typu môže byť predvolená len jedna adresa.
        static::saved(function (self $address) {
            if (! $address->is_default) {
                return;
            }

            static::query()
                ->where('organization_id', $address->organization_id)
                ->where('type', $address->type->value)
                ->whereKeyNot($address->id)
                ->update(['is_default' => false]);
        });
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @param Builder<OrganizationAddress> $query */
    public function scopeOfType(Builder $query, AddressType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function streetLine(): string
    {
        return trim($this->street.' '.($this->street_no ?? ''));
    }

    /** Jednoriadkový zápis pre zoznamy. */
    public function line(): string
    {
        return collect([
            $this->streetLine(),
            trim($this->postal_code.' '.$this->city),
            $this->country,
        ])->filter()->implode(', ');
    }

    /** Riadky pre obálku alebo prepravný štítok. */
    /** @return array<int, string> */
    public function envelopeLines(): array
    {
        return array_values(array_filter([
            $this->recipient,
            $this->streetLine(),
            trim($this->postal_code.' '.$this->city),
            $this->country !== 'SK' ? $this->country : null,
        ]));
    }
}
