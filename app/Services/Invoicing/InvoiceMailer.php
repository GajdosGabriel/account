<?php

namespace App\Services\Invoicing;

use App\Enums\InvoiceStatus;
use App\Mail\InvoiceMail;
use App\Mail\InvoiceReminderMail;
use App\Models\Invoice;
use DomainException;
use Illuminate\Support\Facades\Mail;

/**
 * Odosielanie dokladov zákazníkom.
 *
 * Každé odoslanie sa zapisuje do histórie dokladu – vrátane adresy.
 * Keď zákazník o mesiac tvrdí, že nič nedostal, je to čierne na bielom.
 */
class InvoiceMailer
{
    public function __construct(private readonly InvoiceRenderer $renderer) {}

    /**
     * Pošle faktúru zákazníkovi.
     *
     * @param  string|null  $to  Prepíše adresu zo snapshotu (napr. jednorazové preposlanie).
     */
    public function send(Invoice $invoice, ?string $to = null, ?string $message = null): Invoice
    {
        if ($invoice->isDraft()) {
            throw new DomainException('Koncept sa neposiela. Najprv doklad vystav.');
        }

        $this->ensureDeliverable($invoice);

        $recipient = $to ?: $invoice->recipientEmail();

        if ($this->testing()) {
            return $this->sendTest($invoice, $recipient, new InvoiceMail($invoice, $message), 'Doklad skúšobne odoslaný');
        }

        if (blank($recipient)) {
            throw new DomainException('Firma nemá vyplnený e-mail na faktúry.');
        }

        $verified = $this->recipientIsVerified($invoice, $recipient);

        if (! $verified && config('invoicing.require_verified_billing_email')) {
            throw new DomainException(
                "Adresa {$recipient} nie je potvrdená. Pošli firme overovací e-mail alebo doklad odovzdaj inak.",
            );
        }

        // PDF si vygenerujeme a uložíme teraz, nie až vo fronte –
        // ak niečo padne, dozvieme sa to hneď a nie z logu.
        if ($this->renderer->pdfAvailable()) {
            $this->renderer->store($invoice);
        }

        $mail = Mail::to($recipient);

        // Skrytá kópia pre účtovníka / archív (Faktúry → Nastavenia).
        if (filled($bcc = config('invoicing.sending.bcc'))) {
            $mail->bcc($bcc);
        }

        $mail->send(new InvoiceMail($invoice, $message));

        $invoice->forceFill([
            'status' => $invoice->status === InvoiceStatus::Issued ? InvoiceStatus::Sent : $invoice->status,
            'sent_at' => now(),
            'sent_to' => $recipient,
            'sent_count' => $invoice->sent_count + 1,
        ])->save();

        $invoice->recordEvent(
            'sent',
            $verified
                ? "Odoslané na {$recipient}."
                : "Odoslané na {$recipient} – adresa nie je potvrdená.",
            [
                'email' => $recipient,
                'attempt' => $invoice->sent_count,
                // Keď zákazník o mesiac tvrdí, že nič nedostal, toto je prvá
                // vec, ktorú treba vedieť: išlo to na overenú adresu?
                'recipient_verified' => $verified,
            ],
        );

        return $invoice->refresh();
    }

    /**
     * Bola adresa príjemcu potvrdená?
     *
     * Ručne zadaná adresa (jednorazové preposlanie) je potvrdená len vtedy,
     * keď sa zhoduje s tou overenou – inak by sa dala kontrola obísť
     * prepísaním políčka.
     */
    protected function recipientIsVerified(Invoice $invoice, string $recipient): bool
    {
        $organization = $invoice->organization;

        return $organization !== null
            && $organization->hasVerifiedBillingEmail()
            && $organization->emailsMatch($organization->billing_email_verified_address, $recipient);
    }

    /**
     * Upomienka. `tone` je friendly|firm|final.
     */
    public function remind(Invoice $invoice, string $tone = 'friendly'): Invoice
    {
        if (! $invoice->isOverdue()) {
            throw new DomainException('Doklad nie je po splatnosti.');
        }

        $this->ensureDeliverable($invoice);

        $recipient = $invoice->recipientEmail();

        // Testovacia upomienka nezvyšuje počítadlo – po prepnutí naostro
        // musí zákazník dostať aj prvú úroveň.
        if ($this->testing()) {
            return $this->sendTest($invoice, $recipient, new InvoiceReminderMail($invoice, $tone), "Upomienka ({$tone}) skúšobne odoslaná");
        }

        if (blank($recipient)) {
            throw new DomainException('Firma nemá vyplnený e-mail na faktúry.');
        }

        $mailer = Mail::to($recipient);

        if (config('invoicing.reminders.copy_to_supplier') && filled($supplier = config('invoicing.supplier.email'))) {
            $mailer->bcc($supplier);
        }

        $mailer->send(new InvoiceReminderMail($invoice, $tone));

        $invoice->forceFill([
            'last_reminder_at' => now(),
            'reminder_count' => $invoice->reminder_count + 1,
        ])->save();

        $invoice->recordEvent('reminded', "Upomienka ({$tone}) odoslaná na {$recipient}.", [
            'tone' => $tone,
            'days_overdue' => $invoice->daysOverdue(),
        ]);

        return $invoice->refresh();
    }

    /* ---------------------------------------------------------------
     | Režim odosielania (Faktúry → Nastavenia → Odosielanie)
     |---------------------------------------------------------------*/

    /** live | redirect | off – neznáma hodnota sa berie ako naostro. */
    public function mode(): string
    {
        $mode = config('invoicing.sending.mode', 'live');

        return in_array($mode, InvoiceSettings::SENDING_MODES, true) ? $mode : 'live';
    }

    /** Všetko ide na testovaciu adresu namiesto zákazníka. */
    public function testing(): bool
    {
        return $this->mode() === 'redirect';
    }

    /**
     * Prečo sa doklad nedá poslať – null = dá sa.
     *
     * Jedno miesto pre ručné odoslanie, fakturačný beh, upomienky aj
     * InvoicePolicy, aby sa tlačidlo neukázalo tam, kde by odoslanie zlyhalo.
     */
    public function blockedReason(Invoice $invoice): ?string
    {
        return match (true) {
            $this->mode() === 'off' => 'Odosielanie dokladov je vypnuté (Faktúry → Nastavenia).',
            $this->testing() && blank(config('invoicing.sending.redirect_to')) => 'Testovací režim nemá nastavenú adresu, kam doklady posielať (Faktúry → Nastavenia).',
            // V testovacom režime ide všetko nám, takže aj testovaciu firmu sa dá vyskúšať.
            ! $this->testing() && ! $invoice->organization?->receivesInvoices() => 'Firma má nastavené „Doklady neposielať“ – zákazníkovi sa nič neodošle.',
            default => null,
        };
    }

    protected function ensureDeliverable(Invoice $invoice): void
    {
        if ($reason = $this->blockedReason($invoice)) {
            throw new DomainException($reason);
        }
    }

    /**
     * Testovacie odoslanie: mail ide na testovaciu adresu, bez skrytých
     * kópií, a doklad sa nijako nezmení (stav, počítadlá). Ostane len
     * záznam v histórii.
     */
    protected function sendTest(Invoice $invoice, ?string $customer, InvoiceMail|InvoiceReminderMail $mail, string $what): Invoice
    {
        $to = config('invoicing.sending.redirect_to');
        $mail->testFor = $customer ?: 'bez e-mailu';

        Mail::to($to)->send($mail);

        $invoice->recordEvent('test_sent', "{$what} na {$to} (zákazník {$mail->testFor} nič nedostal).", [
            'email' => $to,
            'customer_email' => $customer,
            'mail' => $mail::class,
        ]);

        return $invoice->refresh();
    }
}
