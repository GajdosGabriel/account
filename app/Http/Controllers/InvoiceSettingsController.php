<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Invoicing\InvoiceRenderer;
use App\Services\Invoicing\InvoiceSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Nastavenia fakturácie – upomienky, odosielanie, predvolené hodnoty
 * dokladu a automatická fakturácia predplatného.
 */
class InvoiceSettingsController extends Controller
{
    public function __construct(private readonly InvoiceSettings $settings) {}

    public function edit(InvoiceRenderer $renderer): Response
    {
        $this->authorize('settings', Invoice::class);

        return Inertia::render('Invoices/Settings', [
            'settings' => $this->settings->form(),
            'defaults' => $this->settings->defaults(),
            'vat_rates' => config('invoicing.vat.rates'),
            'qr_formats' => InvoiceSettings::QR_FORMATS,
            'supplier_email' => config('invoicing.supplier.email'),
            'pdf_available' => $renderer->pdfAvailable(),
            'overdue_count' => Invoice::overdue()->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('settings', Invoice::class);

        $validator = Validator::make($request->all(), $this->settings->rules());
        $validator->after(fn ($v) => $this->settings->after($v));

        $changed = $this->settings->save($validator->validate(), $request->user()?->id);

        return back()->with('success', $changed
            ? __('invoices.settings.saved')
            : __('invoices.settings.unchanged'));
    }

    public function reset(): RedirectResponse
    {
        $this->authorize('settings', Invoice::class);

        $this->settings->reset();

        return back()->with('success', __('invoices.settings.reset_done'));
    }
}
