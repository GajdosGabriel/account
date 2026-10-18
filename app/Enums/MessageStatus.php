<?php

namespace App\Enums;

enum MessageStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Bounced = 'bounced';
    case Received = 'received';
    case AutoReply = 'auto_reply';

    public function label(): string
    {
        return __('enums.message_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued, self::AutoReply => 'slate',
            self::Sent => 'emerald',
            self::Failed, self::Bounced => 'rose',
            self::Received => 'sky',
        };
    }
}
