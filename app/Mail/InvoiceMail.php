<?php

namespace App\Mail;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Services\Invoicing\InvoiceRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail s faktúrou v prílohe.
 *
 * PDF sa neprikladá zo súboru, ale generuje sa cez renderer – tak sa
 * nikdy nestane, že zákazník dostane prílohu z iného dokladu.
 */
class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly ?string $message = null,
    ) {}

    public function envelope(): Envelope
    {
        $supplier = $this->invoice->supplier_snapshot ?: config('invoicing.supplier');

        $subject = match ($this->invoice->type) {
            InvoiceType::Proforma => "Zálohová faktúra č. {$this->invoice->number}",
            InvoiceType::CreditNote => "Dobropis č. {$this->invoice->number}",
            default => "Faktúra č. {$this->invoice->number}",
        };

        return new Envelope(
            subject: $subject.' – '.$supplier['name'],
            replyTo: filled($supplier['email'] ?? null) ? [$supplier['email']] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
            with: [
                'invoice' => $this->invoice,
                'supplier' => $this->invoice->supplier_snapshot ?: config('invoicing.supplier'),
                'customMessage' => $this->message,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $renderer = app(InvoiceRenderer::class);

        if (! $renderer->pdfAvailable()) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $renderer->contents($this->invoice), $this->invoice->filename())
                ->withMime('application/pdf'),
        ];
    }
}
