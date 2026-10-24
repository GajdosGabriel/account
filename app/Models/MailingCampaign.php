<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kampaň: jednorazová ponuka všetkým kontaktom, alebo automatizácia
 * viazaná na udalosť projektu (`trigger_event`) s oneskorením.
 */
class MailingCampaign extends Model
{
    public const DRAFT = 'draft';

    public const QUEUED = 'queued';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    /** Ponuka: len kontaktom so súhlasom, s odhlásením. */
    public const OFFER = 'offer';

    /** Oznámenie o službe: všetkým v segmente, bez reklamy a bez odhlásenia. */
    public const SERVICE = 'service';

    /** Najdlhšie povolené oneskorenie (365 dní). */
    public const MAX_DELAY_HOURS = 8760;

    protected $fillable = [
        'product_id', 'mailbox_id', 'layout_id', 'name', 'kind', 'audience', 'subject', 'heading', 'body',
        'button_label', 'button_url', 'coupon_code', 'coupon_valid_to', 'status', 'scheduled_at',
        'trigger_event', 'delay_hours', 'track_clicks', 'track_opens', 'reply_in_thread', 'quote_event', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'coupon_valid_to' => 'date',
            'scheduled_at' => 'datetime',
            'delay_hours' => 'integer',
            'track_clicks' => 'boolean',
            'track_opens' => 'boolean',
            'reply_in_thread' => 'boolean',
            'quote_event' => 'boolean',
            'audience' => 'array',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** @return BelongsTo<Mailbox, $this> */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    /** @return BelongsTo<MailingLayout, $this> */
    public function layout(): BelongsTo
    {
        return $this->belongsTo(MailingLayout::class);
    }

    /** @return HasMany<MailingDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(MailingDelivery::class, 'campaign_id');
    }

    public function isAutomation(): bool
    {
        return filled($this->trigger_event);
    }

    public function isService(): bool
    {
        return $this->kind === self::SERVICE;
    }

    /**
     * Štítky segmentu: `any` (aspoň jeden) alebo `none` (žiadny).
     *
     * @return list<string>
     */
    public function audienceTags(string $which): array
    {
        return array_values(array_filter((array) ($this->audience[$which] ?? []), 'is_string'));
    }

    /** Odpoveď vo vlákne je servisná správa: bez odhlásenia a bez merania. */
    public function isReply(): bool
    {
        return $this->reply_in_thread && $this->isAutomation();
    }

    public function couponExpired(): bool
    {
        return $this->coupon_valid_to !== null && $this->coupon_valid_to->copy()->endOfDay()->isPast();
    }
}
