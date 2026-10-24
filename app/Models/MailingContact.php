<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Adresát ponúk v jednom projekte.
 *
 * Odhlásenie platí len pre ponuky daného projektu. Trvalo nedoručiteľná
 * adresa je v `mail_suppressions` a blokuje všetky maily projektu.
 *
 * Kontakt, ktorý projekt odstráni, je len skrytý (soft delete) – nedostane
 * žiadnu kampaň, ale keď ho projekt pošle znova, obnoví sa s odhlásením,
 * tokenom aj históriou. Ručné zmazanie v Accounte je trvalé.
 */
class MailingContact extends Model
{
    use SoftDeletes;

    public const SOFT_BOUNCE_LIMIT = 3;

    protected $fillable = [
        'product_id', 'organization_id', 'email', 'name', 'permission_note', 'permission_at',
        'created_by', 'token', 'unsubscribed_at', 'unsubscribed_changed_at', 'bounced_at', 'bounce_reason', 'soft_bounces',
        'attributes', 'tags',
    ];

    protected function casts(): array
    {
        return [
            'permission_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'unsubscribed_changed_at' => 'datetime',
            'bounced_at' => 'datetime',
            'attributes' => 'array',
            'tags' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $contact) {
            $contact->email = strtolower(trim($contact->email));
            $contact->token ??= Str::random(64);

            // Čas zmeny odhlásenia rozhoduje, či ho prepíše stav poslaný z projektu.
            if ($contact->isDirty('unsubscribed_at') && ! $contact->isDirty('unsubscribed_changed_at')) {
                $contact->unsubscribed_changed_at = now();
            }
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
        $query->consented()->whereNull('unsubscribed_at')->notBlocked();
    }

    /**
     * Súhlas s ponukami. Kontakt z projektu bez súhlasu je v adresári len
     * pre oznámenia o službe; staršie kontakty z importu majú aspoň poznámku.
     *
     * @param  Builder<self>  $query
     */
    public function scopeConsented(Builder $query): void
    {
        $query->where(fn ($q) => $q->whereNotNull('permission_at')->orWhereNotNull('permission_note'));
    }

    /**
     * Adresa sa dá doručiť: kontakt nie je vylúčený po bounce ani blokovaný.
     *
     * @param  Builder<self>  $query
     */
    public function scopeNotBlocked(Builder $query): void
    {
        $query->whereNull('bounced_at')
            ->whereNotExists(fn ($q) => $q->from('mail_suppressions')
                ->whereColumn('mail_suppressions.product_id', 'mailing_contacts.product_id')
                ->whereColumn('mail_suppressions.email', 'mailing_contacts.email'));
    }

    /**
     * Adresáti kampane: projekt, druh (ponuka / oznámenie o službe) a segment
     * podľa štítkov – `any` aspoň jeden z nich, `none` žiadny z nich.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAudience(Builder $query, MailingCampaign $campaign): void
    {
        $query->where('mailing_contacts.product_id', $campaign->product_id);
        $campaign->isService() ? $query->notBlocked() : $query->reachable();

        $any = $campaign->audienceTags('any');
        $none = $campaign->audienceTags('none');

        if ($any) {
            $query->where(fn ($q) => collect($any)->each(fn ($tag) => $q->orWhereJsonContains('tags', $tag)));
        }

        foreach ($none as $tag) {
            $query->where(fn ($q) => $q->whereNull('tags')->orWhereJsonDoesntContain('tags', $tag));
        }
    }

    public function hasConsent(): bool
    {
        return $this->permission_at !== null || $this->permission_note !== null;
    }

    /** Bez kampane platí pravidlo ponuky. */
    public function isReachable(?MailingCampaign $campaign = null): bool
    {
        $service = $campaign?->isService() ?? false;

        return ($service || ($this->hasConsent() && ! $this->unsubscribed_at)) && ! $this->bounced_at
            && ! MailSuppression::query()->where('product_id', $this->product_id)->where('email', $this->email)->exists()
            && (! $campaign || $this->matchesAudience($campaign));
    }

    public function matchesAudience(MailingCampaign $campaign): bool
    {
        $tags = $this->tags ?? [];
        $any = $campaign->audienceTags('any');

        return (! $any || array_intersect($any, $tags)) && ! array_intersect($campaign->audienceTags('none'), $tags);
    }
}
