<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ConversationAttachment extends Model
{
    protected $fillable = ['uuid', 'conversation_message_id', 'filename', 'mime', 'size', 'disk', 'path'];

    protected static function booted(): void
    {
        static::creating(function (self $attachment) {
            $attachment->uuid ??= (string) Str::uuid();
        });

        static::deleting(function (self $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<ConversationMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }

    public function contents(): ?string
    {
        return Storage::disk($this->disk)->get($this->path);
    }

    /**
     * Dočasný podpísaný odkaz – projekt si prílohu stiahne z webhooku
     * bez service tokenu, ale len pár dní.
     */
    public function temporaryUrl(int $days = 7): string
    {
        return URL::temporarySignedRoute(
            'messaging.attachments.download',
            now()->addDays($days),
            ['attachment' => $this->uuid],
        );
    }
}
