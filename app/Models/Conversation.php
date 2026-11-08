<?php

namespace App\Models;

use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Vlákno s jedným kontaktom o jednej veci v projekte.
 *
 * Kľúč je (projekt, ref_type, ref_id, e-mail). Account nevie, čo je
 * „canal“ alebo „order“ – je to len text, ktorý sa vracia projektu vo
 * webhooku. Tá istá osoba v dvoch projektoch má dve konverzácie.
 */
class Conversation extends Model
{
    protected $fillable = [
        'product_id', 'mailbox_id', 'organization_id',
        'ref_type', 'ref_id', 'ref_label', 'ref_url', 'thread_ref',
        'contact_email', 'contact_name', 'subject', 'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'last_inbound_at' => 'datetime',
            'last_outbound_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $conversation) {
            $conversation->uuid ??= (string) Str::uuid();
        });

        static::saving(function (self $conversation) {
            $conversation->contact_email = strtolower(trim($conversation->contact_email));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
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

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withTrashed();
    }

    /** @return HasMany<ConversationMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('created_at')->orderBy('id');
    }

    /** @param Builder<self> $query */
    public function scopeUnassigned(Builder $query): void
    {
        $query->whereNull('ref_type');
    }

    /**
     * Inbox ukazuje len to, na čo treba reagovať: vlákna, kde napísal
     * človek – klient (okrem automatickej odpovede) alebo operátor z inboxu.
     * Vlákno s len automatickými mailmi (potvrdenie objednávky, kampaň) sa
     * objaví až s odpoveďou klienta; dovtedy je len pod „aj automatické“.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleInInbox(Builder $query): void
    {
        $query->whereNotNull('last_message_at')
            ->whereExists(fn ($q) => $q->selectRaw('1')
                ->from('conversation_messages')
                ->whereColumn('conversation_messages.conversation_id', 'conversations.id')
                ->where(fn ($w) => $w
                    ->where(fn ($in) => $in->where('direction', ConversationMessage::IN)
                        ->where('status', '!=', MessageStatus::AutoReply->value))
                    ->orWhere('source', ConversationMessage::SOURCE_INBOX)));
    }

    /** @param Builder<self> $query */
    public function scopeNotSpam(Builder $query): void
    {
        $query->where('status', '!=', ConversationStatus::Spam->value);
    }

    public function isSpam(): bool
    {
        return $this->status === ConversationStatus::Spam;
    }

    public function isAssigned(): bool
    {
        return $this->ref_type !== null;
    }

    /** @return array{type: ?string, id: ?string, label: ?string, url: ?string} */
    public function ref(): array
    {
        return [
            'type' => $this->ref_type,
            'id' => $this->ref_id,
            'label' => $this->ref_label,
            'url' => $this->ref_url,
        ];
    }

    /**
     * Message-ID správ vlákna – do hlavičky References.
     *
     * @return array<int, string>
     */
    public function threadMessageIds(): array
    {
        $ids = $this->messages()
            ->whereNotNull('message_id')
            ->pluck('message_id')
            ->all();

        return $this->thread_ref ? [$this->thread_ref, ...$ids] : $ids;
    }
}
