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

        $recipient = $to ?: $invoice->recipientEmail();

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

        Mail::to($recipient)->send(new InvoiceMail($invoice, $message));

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

        $recipient = $invoice->recipientEmail();

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
}
