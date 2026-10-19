<?php

namespace App\Services\Messaging;

use App\Models\ConversationAttachment;
use App\Models\ConversationMessage;
use App\Models\Mailbox;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;

/**
 * Odošle uloženú správu cez schránku projektu.
 *
 * Hlavičky drží vlákno pokope: vlastné Message-ID (podľa neho spárujeme
 * odpoveď), In-Reply-To a References na predchádzajúce správy, aby
 * klient videl celú konverzáciu v jednom vlákne.
 */
class OutboundMailer
{
    /** Hlavičky, ktoré smie projekt doplniť (ukladajú sa do `meta.headers`). */
    public const ALLOWED_HEADERS = ['List-Unsubscribe', 'List-Unsubscribe-Post'];

    public function send(ConversationMessage $message): void
    {
        $mailbox = $message->mailbox ?? throw MessagingException::noMailbox();
        $conversation = $message->conversation;

        $view = array_filter([
            'html' => $message->body_html !== null ? new HtmlString($message->body_html) : null,
            'text' => $message->body_text !== null ? new HtmlString($message->body_text) : null,
        ]);

        $this->mailer($mailbox)->send($view, [], function (Message $mail) use ($message, $mailbox, $conversation) {
            $mail->from($mailbox->from_address, $mailbox->from_name);
            $mail->replyTo($mailbox->from_address, $mailbox->from_name);
            $mail->to($message->to_email, $conversation->contact_name);
            $mail->subject((string) $message->subject);

            $headers = $mail->getSymfonyMessage()->getHeaders();
            $headers->addIdHeader('Message-ID', $message->message_id);

            if ($message->in_reply_to) {
                $headers->addIdHeader('In-Reply-To', $message->in_reply_to);
            }

            if ($message->references) {
                $headers->addIdHeader('References', preg_split('/\s+/', trim($message->references)));
            }

            $headers->addTextHeader('X-Conversation', $conversation->uuid);

            foreach ($message->meta['headers'] ?? [] as $name => $value) {
                if (in_array($name, self::ALLOWED_HEADERS, true)) {
                    $headers->addTextHeader($name, (string) $value);
                }
            }

            /** @var ConversationAttachment $attachment */
            foreach ($message->attachments as $attachment) {
                $mail->attachData((string) $attachment->contents(), $attachment->filename, array_filter([
                    'mime' => $attachment->mime,
                ]));
            }
        });
    }

    /**
     * Vlastné SMTP schránky, alebo predvolený mailer Accountu.
     *
     * Mimo produkcie ide všetko cez predvolený mailer (Mailtrap, log), aby
     * z lokálnej databázy neodišiel mail skutočnému zákazníkovi.
     */
    public function mailer(Mailbox $mailbox): Mailer
    {
        if (! $mailbox->hasOwnSmtp() || ! config('accounts.messaging.use_mailbox_smtp')) {
            return Mail::mailer();
        }

        return Mail::build([
            'transport' => 'smtp',
            'host' => $mailbox->smtp_host,
            'port' => $mailbox->smtp_port ?: 587,
            'encryption' => $mailbox->smtp_encryption ?: null,
            'scheme' => $mailbox->smtp_encryption === 'ssl' ? 'smtps' : null,
            'username' => $mailbox->smtp_username,
            'password' => $mailbox->smtp_password,
            'timeout' => 20,
        ]);
    }
}
