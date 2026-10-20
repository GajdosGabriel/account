<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Jeden e-mail kampane jednému adresátovi (pri automatizácii k jednej udalosti). */
class MailingDelivery extends Model
{
    public const PENDING = 'pending';

    public const SENDING = 'sending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    public const BOUNCED = 'bounced';

    protected $fillable = [
        'campaign_id', 'contact_id', 'email', 'status', 'ref_type', 'ref_id', 'dedupe_key', 'payload',
        'event_at', 'due_at', 'attempted_at', 'sent_at', 'message_id', 'conversation_message_id', 'error',
        'open_token', 'opened_at', 'clicked_at', 'open_count',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'event_at' => 'datetime',
            'due_at' => 'datetime',
            'attempted_at' => 'datetime',
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MailingCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MailingCampaign::class, 'campaign_id');
    }

    /** @return BelongsTo<MailingContact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(MailingContact::class, 'contact_id');
    }

    /** @return HasMany<MailingLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(MailingLink::class, 'delivery_id');
    }

    /** @return array<string, mixed> */
    public function variables(): array
    {
        return (array) ($this->payload['variables'] ?? []);
    }
}
