<?php

namespace App\Mail;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Žiadosť o potvrdenie adresy, na ktorú budú chodiť faktúry.
 *
 * Odosiela sa na adresu, ktorá sa má overiť – nie na tú overenú. To je celý
 * zmysel: keď je v adrese preklep, e-mail nepríde a firma zostane neoverená.
 */
class BillingEmailVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Organization $organization,
        public readonly string $verificationUrl,
    ) {}

    public function envelope(): Envelope
    {
        $supplier = config('invoicing.supplier');

        return new Envelope(
            subject: 'Potvrďte e-mail na faktúry – '.$supplier['name'],
            replyTo: filled($supplier['email'] ?? null) ? [$supplier['email']] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.billing-email-verification',
            with: [
                'organization' => $this->organization,
                'supplier' => config('invoicing.supplier'),
                'url' => $this->verificationUrl,
            ],
        );
    }
}
