<?php

namespace App\Mail;

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
 * Upomienka k faktúre po splatnosti.
 *
 * Tón sa stupňuje – prvá upomienka je zdvorilá pripomienka, posledná
 * oznamuje pozastavenie služby. Nikto nemá rád vymáhanie, ale mlčať
 * a potom vypnúť službu bez varovania je horšie.
 */
class InvoiceReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly string $tone = 'friendly',
    ) {}

    public function envelope(): Envelope
    {
        $supplier = $this->invoice->supplier_snapshot ?: config('invoicing.supplier');

        $subject = match ($this->tone) {
            'final' => "Posledná výzva na úhradu faktúry č. {$this->invoice->number}",
            'firm' => "Upomienka – neuhradená faktúra č. {$this->invoice->number}",
            default => "Pripomenutie splatnosti faktúry č. {$this->invoice->number}",
        };

        return new Envelope(
            subject: $subject,
            replyTo: filled($supplier['email'] ?? null) ? [$supplier['email']] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-reminder',
            with: [
                'invoice' => $this->invoice,
                'supplier' => $this->invoice->supplier_snapshot ?: config('invoicing.supplier'),
                'tone' => $this->tone,
                'daysOverdue' => $this->invoice->daysOverdue(),
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
