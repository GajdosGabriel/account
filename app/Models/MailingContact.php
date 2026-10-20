<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Adresát ponúk v jednom projekte.
 *
 * Odhlásenie platí len pre ponuky daného projektu. Trvalo nedoručiteľná
 * adresa je v `mail_suppressions` a blokuje všetky maily projektu.
 */
class MailingContact extends Model
{
    public const SOFT_BOUNCE_LIMIT = 3;

    protected $fillable = [
        'product_id', 'organization_id', 'email', 'name', 'permission_note', 'permission_at',
        'created_by', 'token', 'unsubscribed_at', 'bounced_at', 'bounce_reason', 'soft_bounces', 'attributes',
    ];

    protected function casts(): array
    {
        return [
            'permission_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'bounced_at' => 'datetime',
            'attributes' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $contact) {
            $contact->email = strtolower(trim($contact->email));
            $contact->token ??= Str::random(64);
        });
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Kto smie dostať ponuku: neodhlásený, nevylúčený a adresa nie je blokovaná.
     *
     * @param  Builder<self>  $query
     */
    public function scopeReachable(Builder $query): void
    {
        $query->whereNull('unsubscribed_at')
            ->whereNull('bounced_at')
            ->whereNotExists(fn ($q) => $q->from('mail_suppressions')
                ->whereColumn('mail_suppressions.product_id', 'mailing_contacts.product_id')
                ->whereColumn('mail_suppressions.email', 'mailing_contacts.email'));
    }

    public function isReachable(): bool
    {
        return ! $this->unsubscribed_at && ! $this->bounced_at
            && ! MailSuppression::query()->where('product_id', $this->product_id)->where('email', $this->email)->exists();
    }
}
