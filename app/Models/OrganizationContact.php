<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganizationContact extends Model
{
    use SoftDeletes;

    public const TYPES = ['general', 'billing', 'technical', 'statutory'];

    protected $fillable = [
        'organization_id', 'type', 'name', 'position',
        'email', 'phone', 'note', 'is_primary',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(function (self $contact) {
            if (! $contact->is_primary) {
                return;
            }

            static::query()
                ->where('organization_id', $contact->organization_id)
                ->whereKeyNot($contact->id)
                ->update(['is_primary' => false]);
        });
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'billing' => 'Fakturácia',
            'technical' => 'Technický',
            'statutory' => 'Štatutár',
            default => 'Všeobecný',
        };
    }
}
