<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use RuntimeException;

/**
 * Vykreslenie dokladu do HTML alebo PDF.
 *
 * HTML náhľad a PDF používajú tú istú Blade šablónu – čo vidíš v prehliadači,
 * to príde zákazníkovi do schránky. Žiadne dve verzie faktúry, ktoré sa
 * po pol roku rozídu.
 */
class InvoiceRenderer
{
    public function __construct(private readonly PaymentQrGenerator $qr) {}

    /** HTML náhľad – rovnaká šablóna, len bez PDF špecifík. */
    public function html(Invoice $invoice): string
    {
        return View::make('invoices.document', $this->data($invoice, pdf: false))->render();
    }

    /** Binárny obsah PDF. */
    public function pdf(Invoice $invoice): string
    {
        $this->assertPdfAvailable();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.document', $this->data($invoice, pdf: true));

        $pdf->setPaper('a4');
        $pdf->setOptions([
            'isRemoteEnabled' => true,   // kvôli data: URI s QR kódom
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
            'dpi' => 96,
        ]);

        return $pdf->output();
    }

    /**
     * Uloží PDF na disk a zapamätá si cestu.
     * Vystavený doklad sa nemení, takže sa PDF generuje len raz.
     */
    public function store(Invoice $invoice, bool $force = false): string
    {
        $disk = Storage::disk(config('invoicing.storage.disk'));

        if (! $force && $invoice->pdf_path && $disk->exists($invoice->pdf_path)) {
            return $invoice->pdf_path;
        }

        $path = trim(config('invoicing.storage.path'), '/')
            .'/'.($invoice->issued_at?->format('Y') ?? date('Y'))
            .'/'.$invoice->filename();

        $disk->put($path, $this->pdf($invoice));

        $invoice->forceFill(['pdf_path' => $path])->save();

        return $path;
    }

    /** Načíta uložené PDF, prípadne ho dogeneruje. */
    public function contents(Invoice $invoice): string
    {
        if ($invoice->isDraft()) {
            // Koncept sa neukladá – čísla ani snapshoty ešte nie sú finálne.
            return $this->pdf($invoice);
        }

        $disk = Storage::disk(config('invoicing.storage.disk'));
        $path = $this->store($invoice);

        return (string) $disk->get($path);
    }

    public function pdfAvailable(): bool
    {
        return class_exists(\Barryvdh\DomPDF\Facade\Pdf::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(Invoice $invoice, bool $pdf): array
    {
        $invoice->loadMissing(['items', 'organization', 'parent']);

        return [
            'invoice' => $invoice,
            'pdf' => $pdf,
            'qr' => $this->qr->forInvoice($invoice),
            'logoData' => $this->logo(),
        ];
    }

    /** Logo sa do PDF vkladá ako data URI – dompdf tak nemusí nič sťahovať. */
    protected function logo(): ?string
    {
        $path = config('invoicing.supplier.logo');

        if (blank($path)) {
            return null;
        }

        $absolute = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)
            ? $path
            : public_path($path);

        if (! is_file($absolute)) {
            return null;
        }

        $mime = match (strtolower(pathinfo($absolute, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            default => 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($absolute));
    }

    protected function assertPdfAvailable(): void
    {
        if ($this->pdfAvailable()) {
            return;
        }

        throw new RuntimeException(
            'Na generovanie PDF chýba knižnica dompdf. Spusti: composer require barryvdh/laravel-dompdf'
        );
    }
}
