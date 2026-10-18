<?php

namespace App\Notifications;

use App\Services\AccountClient;
use App\Services\AccountMessageRejected;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Email;

/**
 * Notifikačný kanál, ktorý pošle mail cez Account namiesto lokálneho SMTP.
 *
 * Notifikácia zostáva, ako bola – `toMail()` so šablónou a prekladmi
 * projektu. Navyše povie, o akej veci píše:
 *
 *   public function via($notifiable): array
 *   {
 *       return [AccountMailChannel::class];
 *   }
 *
 *   public function accountRef($notifiable): array
 *   {
 *       return ['type' => 'canal', 'id' => $this->canal->id,
 *               'label' => 'Kanál: '.$this->canal->name,
 *               'url' => url("/admin/canals/{$this->canal->id}")];
 *   }
 *
 * Voliteľne `accountKind()` (napr. 'canal.outreach') a
 * `accountOrganization($notifiable)` (uuid firmy z Accountu).
 *
 * Odpoveď klienta príde webhookom ako AccountMessageEvent s rovnakým ref.
 */
class AccountMailChannel
{
    public function __construct(private readonly AccountClient $account) {}

    /** @return array<string, mixed>|null odpoveď Accountu (konverzácia a správa) */
    public function send(object $notifiable, Notification $notification): ?array
    {
        if (! method_exists($notification, 'toMail') || ! method_exists($notification, 'accountRef')) {
            throw new \LogicException($notification::class.' musí mať toMail() aj accountRef().');
        }

        $to = $this->recipient($notifiable, $notification);

        if (! $to) {
            return null;
        }

        /** @var MailMessage $mail */
        $mail = $notification->toMail($notifiable);
        $html = (string) $mail->render();

        $payload = array_filter([
            'to' => $to['email'],
            'to_name' => $to['name'],
            'subject' => $mail->subject ?: config('app.name'),
            'html' => $html,
            'ref' => $notification->accountRef($notifiable),
            'kind' => method_exists($notification, 'accountKind') ? $notification->accountKind() : null,
            'organization_id' => method_exists($notification, 'accountOrganization') ? $notification->accountOrganization($notifiable) : null,
            'meta' => ['notification' => class_basename($notification)],
            'headers' => $this->headers($mail),
            'attachments' => $this->attachments($mail),
        ], fn ($value) => $value !== null && $value !== []);

        try {
            return $this->account->sendMessage($payload);
        } catch (AccountMessageRejected $e) {
            // Opakovanie nepomôže – zalogujeme a frontu nezasekneme.
            Log::notice('Account odmietol správu', ['reason' => $e->reason, 'to' => $to['email'], 'notification' => $notification::class]);

            return null;
        }
    }

    /** @return array{email: string, name: ?string}|null */
    private function recipient(object $notifiable, Notification $notification): ?array
    {
        $route = $notifiable instanceof AnonymousNotifiable
            ? ($notifiable->routes['mail'] ?? $notifiable->routes[self::class] ?? null)
            : $notifiable->routeNotificationFor('mail', $notification);

        if (is_array($route)) {
            $email = array_key_first($route);
            $name = $route[$email];

            return is_string($email) ? ['email' => $email, 'name' => $name] : ['email' => $name, 'name' => null];
        }

        return $route ? ['email' => (string) $route, 'name' => null] : null;
    }

    /**
     * Hlavičky nastavené cez withSymfonyMessage() – Account prijme len
     * List-Unsubscribe a List-Unsubscribe-Post, ostatné zahodí.
     *
     * @return array<string, string>
     */
    private function headers(MailMessage $mail): array
    {
        if ($mail->callbacks === []) {
            return [];
        }

        $email = new Email;

        foreach ($mail->callbacks as $callback) {
            $callback($email);
        }

        $headers = [];

        foreach (['List-Unsubscribe', 'List-Unsubscribe-Post'] as $name) {
            if ($email->getHeaders()->has($name)) {
                $headers[$name] = $email->getHeaders()->get($name)->getBodyAsString();
            }
        }

        return $headers;
    }

    /** @return array<int, array{filename: string, mime: ?string, content: string}> */
    private function attachments(MailMessage $mail): array
    {
        $files = [];

        foreach ($mail->rawAttachments as $raw) {
            $files[] = [
                'filename' => $raw['name'],
                'mime' => $raw['options']['mime'] ?? null,
                'content' => base64_encode($raw['data']),
            ];
        }

        foreach ($mail->attachments as $file) {
            if (is_string($file['file']) && is_file($file['file'])) {
                $files[] = [
                    'filename' => $file['options']['as'] ?? basename($file['file']),
                    'mime' => $file['options']['mime'] ?? null,
                    'content' => base64_encode((string) file_get_contents($file['file'])),
                ];
            }
        }

        return $files;
    }
}
