<?php

namespace App\Models;

use App\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ConversationMessage extends Model
{
    public const OUT = 'out';

    public const IN = 'in';

    /** Kto správu vytvoril: projekt cez API, operátor v inboxe, alebo prišla do schránky. */
    public const SOURCE_PROJECT = 'project';

    public const SOURCE_INBOX = 'inbox';

    public const SOURCE_IMAP = 'imap';

    protected $fillable = [
        'conversation_id', 'mailbox_id', 'direction', 'source', 'kind', 'status',
        'message_id', 'in_reply_to', 'references',
        'from_email', 'from_name', 'to_email', 'subject',
        'body_html', 'body_text', 'reply_text',
        'error', 'bounce_type', 'sent_by_user_id', 'meta', 'imap_uid', 'sent_at', 'read_at',
        'attempts', 'next_attempt_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MessageStatus::class,
            'meta' => 'array',
            'next_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $message) {
            $message->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<Mailbox, $this> */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /** @return HasMany<ConversationAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ConversationAttachment::class);
    }

    public function isInbound(): bool
    {
        return $this->direction === self::IN;
    }
}
