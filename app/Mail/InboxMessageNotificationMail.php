<?php

namespace App\Mail;

use App\Models\ConversationMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Upozornenie správcovi, že do inboxu prišla nová správa od klienta.
 *
 * Nesie hlavičku Auto-Submitted – keby upozornenie skončilo v niektorej
 * zo sledovaných schránok, inbox ho zoberie ako automatický mail a ďalšie
 * upozornenie už nepošle (žiadna slučka).
 */
class InboxMessageNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ConversationMessage $message) {}

    public function envelope(): Envelope
    {
        $from = $this->message->from_name ?: $this->message->from_email;
        $subject = $this->message->subject ?: '(bez predmetu)';

        return new Envelope(subject: "Inbox: {$from} – ".Str::limit($subject, 120));
    }

    public function headers(): Headers
    {
        return new Headers(text: ['Auto-Submitted' => 'auto-generated']);
    }

    public function content(): Content
    {
        $conversation = $this->message->conversation;
        $text = trim((string) ($this->message->reply_text ?: $this->message->body_text));

        return new Content(
            view: 'emails.inbox-message',
            with: [
                'supplier' => config('invoicing.supplier'),
                'inbound' => $this->message,
                'product' => $conversation?->product?->name,
                'label' => $conversation?->ref_label,
                'excerpt' => Str::limit($text, 1500),
                'url' => $conversation ? route('inbox.show', $conversation) : route('inbox.index'),
            ],
        );
    }
}
