<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Http\Requests\InvoiceItemRequest;
use App\Http\Requests\InvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Organization;
use App\Models\Plan;
use App\Services\Invoicing\InvoiceMailer;
use App\Services\Invoicing\InvoiceNumberGenerator;
use App\Services\Invoicing\InvoiceRenderer;
use App\Services\Invoicing\InvoiceService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Back-office fakturácie.
 *
 * Controller sám o sebe nič nepočíta – od validácie po redirect len
 * prekladá HTTP na volania InvoiceService. Autorizáciu robí InvoicePolicy,
 * takže tu nikde nenájdeš `if ($invoice->status === ...)`.
 */
class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly InvoiceMailer $mailer,
        private readonly InvoiceRenderer $renderer,
    ) {}

    /* ===============================================================
     | Zoznam
     |===============================================================*/

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => $request->query('status'),
            'type' => $request->query('type'),
            'organization' => $request->query('organization'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];

        $trashed = $filters['status'] === 'trashed';

        $query = Invoice::query()
            ->when($trashed, fn ($q) => $q->onlyTrashed())
            ->with(['organization'])
            ->when($filters['search'], fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('variable_symbol', 'like', "%{$search}%")
                    ->orWhereHas('organization', fn ($o) => $o
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('ico', 'like', "%{$search}%"));
            }))
            ->when($filters['status'] === 'overdue', fn ($q) => $q->overdue())
            ->when($filters['status'] && ! in_array($filters['status'], ['overdue', 'trashed'], true),
                fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['type'], fn ($q, $type) => $q->where('type', $type))
            ->when($filters['organization'], fn ($q, $uuid) => $q
                ->whereHas('organization', fn ($o) => $o->where('uuid', $uuid)))
            ->when($filters['from'], fn ($q, $from) => $q->whereDate('issued_at', '>=', $from))
            ->when($filters['to'], fn ($q, $to) => $q->whereDate('issued_at', '<=', $to))
            ->orderByRaw('issued_at IS NULL DESC')   // koncepty hore
            ->latest('issued_at')
            ->latest('id');

        return Inertia::render('Invoices/Index', [
            'invoices' => $query->paginate(25)->withQueryString()
                ->through(fn (Invoice $invoice) => (new InvoiceResource($invoice))->resolve()),
            'filters' => $filters,
            'statuses' => InvoiceStatus::options(),
            'types' => InvoiceType::options(),
            'stats' => $this->stats(),
            'trashed_count' => Invoice::onlyTrashed()->count(),
            'organizations' => Organization::orderBy('name')->get(['uuid', 'name'])
                ->map(fn ($o) => ['id' => $o->uuid, 'name' => $o->name]),
            'can' => [
                'create' => $request->user()->can('create', Invoice::class),
                'export' => $request->user()->can('export', Invoice::class),
            ],
        ]);
    }

    /**
     * Čísla do horného pásu – pohľadávky sú to prvé, čo chceš ráno vidieť.
     *
     * @return array<string, mixed>
     */
    protected function stats(): array
    {
        $unpaid = Invoice::unpaid()->selectRaw('COUNT(*) c, SUM(total_cents - paid_cents) s')->first();
        $overdue = Invoice::overdue()->selectRaw('COUNT(*) c, SUM(total_cents - paid_cents) s')->first();

        $paidThisMonth = Invoice::where('status', InvoiceStatus::Paid->value)
            ->whereDate('paid_at', '>=', Carbon::today()->startOfMonth())
            ->sum('total_cents');

        return [
            'drafts' => Invoice::where('status', InvoiceStatus::Draft->value)->count(),
            'unpaid_count' => (int) ($unpaid->c ?? 0),
            'unpaid_cents' => (int) ($unpaid->s ?? 0),
            'overdue_count' => (int) ($overdue->c ?? 0),
            'overdue_cents' => (int) ($overdue->s ?? 0),
            'paid_month_cents' => (int) $paidThisMonth,
        ];
    }

    /* ===============================================================
     | Detail a formulár
     |===============================================================*/

    public function show(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $invoice->load(['items', 'events.user', 'organization', 'parent', 'children']);

        return Inertia::render('Invoices/Show', [
            'invoice' => (new InvoiceResource($invoice))->resolve(),
            'credit_notes' => $invoice->children->map(fn (Invoice $child) => [
                'id' => $child->id,
                'number' => $child->number,
                'type_label' => $child->type->shortLabel(),
                'total' => $child->formatMoney(),
            ]),
            'preview_url' => route('invoices.preview', $invoice),
            'pdf_available' => $this->renderer->pdfAvailable(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Invoice::class);

        $numbers = app(InvoiceNumberGenerator::class);

        return Inertia::render('Invoices/Form', [
            'organizations' => Organization::orderBy('name')->get(['uuid', 'name', 'ico'])
                ->map(fn ($o) => ['id' => $o->uuid, 'name' => $o->name, 'ico' => $o->ico]),
            'organization_id' => $request->query('organization'),
            'types' => InvoiceType::options(),
            'payment_methods' => PaymentMethod::options(),
            'vat_rates' => config('invoicing.vat.rates'),
            'next_numbers' => collect(InvoiceType::cases())
                ->mapWithKeys(fn (InvoiceType $t) => [$t->value => $numbers->preview($t)]),
        ]);
    }

    public function store(InvoiceRequest $request): RedirectResponse
    {
        $this->authorize('create', Invoice::class);

        $organization = Organization::where('uuid', $request->validated('organization_id'))->firstOrFail();

        $type = InvoiceType::from($request->validated('type', InvoiceType::Invoice->value));

        $invoice = $this->invoices->draft($organization, $type, $request->safe()->only([
            'issued_at', 'delivered_at', 'due_at', 'currency', 'locale',
            'payment_method', 'variable_symbol', 'constant_symbol', 'specific_symbol',
            'note', 'internal_note',
        ]));

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Koncept dokladu bol vytvorený. Doplň položky a vystav ho.');
    }

    /** Úprava hlavičky konceptu. */
    public function update(InvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $invoice->fill($request->safe()->only([
            'issued_at', 'delivered_at', 'due_at', 'currency', 'locale',
            'payment_method', 'variable_symbol', 'constant_symbol', 'specific_symbol',
            'rounding_cents', 'note', 'internal_note',
        ]));

        $invoice->save();
        $this->invoices->refreshTotals($invoice);

        return back()->with('success', 'Zmeny boli uložené.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->authorize('delete', $invoice);

        $invoice->delete();

        return redirect()->route('invoices.index')
            ->with('success', "Koncept bol presunutý do koša. Nájdeš ho vo filtri „Zmazané“.");
    }

    /** Vrátenie z koša. */
    public function restore(Invoice $invoice): RedirectResponse
    {
        $this->authorize('restore', $invoice);

        $invoice->restore();
        $invoice->recordEvent('created', 'Koncept bol obnovený z koša.');

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Koncept bol obnovený.');
    }

    /** Nenávratné zmazanie – len konceptov bez čísla. */
    public function forceDelete(Invoice $invoice): RedirectResponse
    {
        $this->authorize('forceDelete', $invoice);

        $invoice->forceDelete();

        return redirect()->route('invoices.index')
            ->with('success', 'Koncept bol natrvalo zmazaný.');
    }

    /* ===============================================================
     | Položky
     |===============================================================*/

    public function storeItem(InvoiceItemRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        return $this->guard(function () use ($request, $invoice) {
            $this->invoices->addItem($invoice, $this->itemPayload($request));

            return back()->with('success', 'Položka bola pridaná.');
        });
    }

    public function updateItem(InvoiceItemRequest $request, Invoice $invoice, InvoiceItem $item): RedirectResponse
    {
        $this->authorize('update', $invoice);
        abort_unless($item->invoice_id === $invoice->id, 404);

        return $this->guard(function () use ($request, $item) {
            $this->invoices->updateItem($item, $this->itemPayload($request));

            return back()->with('success', 'Položka bola upravená.');
        });
    }

    public function destroyItem(Invoice $invoice, InvoiceItem $item): RedirectResponse
    {
        $this->authorize('update', $invoice);
        abort_unless($item->invoice_id === $invoice->id, 404);

        return $this->guard(function () use ($item) {
            $this->invoices->removeItem($item);

            return back()->with('success', 'Položka bola odstránená.');
        });
    }

    /**
     * Vo formulári je cena v eurách, v databáze v stotinách centa.
     *
     * @return array<string, mixed>
     */
    protected function itemPayload(InvoiceItemRequest $request): array
    {
        $data = $request->validated();
        $data['unit_price'] = (int) round(((float) $data['unit_price']) * 10000);

        return $data;
    }

    /* ===============================================================
     | Akcie nad dokladom
     |===============================================================*/

    public function issue(Invoice $invoice): RedirectResponse
    {
        $this->authorize('issue', $invoice);

        return $this->guard(function () use ($invoice) {
            $issued = $this->invoices->issue($invoice);

            return back()->with('success', "Doklad bol vystavený pod číslom {$issued->number}.");
        });
    }

    public function send(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('send', $invoice);

        $data = $request->validate([
            'email' => ['nullable', 'email'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->guard(function () use ($invoice, $data) {
            $sent = $this->mailer->send($invoice, $data['email'] ?? null, $data['message'] ?? null);

            return back()->with('success', "Doklad bol odoslaný na {$sent->sent_to}.");
        });
    }

    public function remind(Invoice $invoice): RedirectResponse
    {
        $this->authorize('remind', $invoice);

        return $this->guard(function () use ($invoice) {
            $tone = match (true) {
                $invoice->reminder_count >= 2 => 'final',
                $invoice->reminder_count === 1 => 'firm',
                default => 'friendly',
            };

            $this->mailer->remind($invoice, $tone);

            return back()->with('success', 'Upomienka bola odoslaná.');
        });
    }

    public function pay(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('pay', $invoice);

        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'paid_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->guard(function () use ($invoice, $data) {
            $this->invoices->recordPayment(
                $invoice,
                isset($data['amount']) ? (int) round($data['amount'] * 100) : null,
                isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : null,
                $data['note'] ?? null,
            );

            return back()->with('success', 'Úhrada bola zaznamenaná.');
        });
    }

    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('cancel', $invoice);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        return $this->guard(function () use ($invoice, $data) {
            $this->invoices->cancel($invoice, $data['reason'] ?? null);

            return back()->with('success', 'Doklad bol stornovaný.');
        });
    }

    public function credit(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('credit', $invoice);

        $data = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*' => ['integer'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->guard(function () use ($invoice, $data) {
            $credit = $this->invoices->creditNote($invoice, $data['items'] ?? null, $data['reason'] ?? null);

            return redirect()->route('invoices.show', $credit)
                ->with('success', "Dobropis č. {$credit->number} bol vystavený.");
        });
    }

    public function convert(Invoice $invoice): RedirectResponse
    {
        $this->authorize('convert', $invoice);

        return $this->guard(function () use ($invoice) {
            $created = $this->invoices->invoiceFromProforma($invoice);

            return redirect()->route('invoices.show', $created)
                ->with('success', 'Koncept riadnej faktúry bol pripravený.');
        });
    }

    /** Kópia dokladu ako nový koncept – šetrí prácu pri opakovanej fakturácii. */
    public function duplicate(Invoice $invoice): RedirectResponse
    {
        $this->authorize('duplicate', $invoice);

        $invoice->loadMissing(['organization', 'items']);

        $copy = $this->invoices->draft($invoice->organization, $invoice->type, [
            'currency' => $invoice->currency,
            'locale' => $invoice->locale,
            'payment_method' => $invoice->payment_method,
            'note' => $invoice->note,
        ]);

        foreach ($invoice->items as $item) {
            $this->invoices->addItem($copy, [
                'product_id' => $item->product_id,
                'plan_id' => $item->plan_id,
                'description' => $item->description,
                'detail' => $item->detail,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'discount_percent' => $item->discount_percent,
                'vat_rate' => $item->vat_rate,
                'sort_order' => $item->sort_order,
            ]);
        }

        return redirect()->route('invoices.show', $copy)
            ->with('success', 'Kópia bola vytvorená ako nový koncept.');
    }

    /* ===============================================================
     | Výstupy
     |===============================================================*/

    /** HTML náhľad – to isté, čo uvidí zákazník v PDF. */
    public function preview(Invoice $invoice): HttpResponse
    {
        $this->authorize('view', $invoice);

        return response($this->renderer->html($invoice))
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    public function download(Request $request, Invoice $invoice): HttpResponse
    {
        $this->authorize('download', $invoice);

        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($this->renderer->contents($invoice))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', $disposition.'; filename="'.$invoice->filename().'"');
    }

    /**
     * Export pre účtovníka.
     *
     *   ?format=csv  – univerzálny, otvorí sa v Exceli aj v Pohode
     *   ?format=xml  – štruktúrovaný, na import do účtovného softvéru
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', Invoice::class);

        $from = $request->query('from', Carbon::today()->startOfMonth()->toDateString());
        $to = $request->query('to', Carbon::today()->endOfMonth()->toDateString());
        $format = $request->query('format', 'csv');

        $invoices = Invoice::query()
            ->with(['organization', 'items'])
            ->whereNotNull('number')
            ->where('status', '!=', InvoiceStatus::Draft->value)
            ->issuedBetween($from, $to)
            ->orderBy('issued_at')
            ->orderBy('number')
            ->get();

        Invoice::whereIn('id', $invoices->pluck('id'))->update(['exported_at' => now()]);

        return $format === 'xml'
            ? $this->exportXml($invoices, $from, $to)
            : $this->exportCsv($invoices, $from, $to);
    }

    /** @param \Illuminate\Support\Collection<int, Invoice> $invoices */
    protected function exportCsv($invoices, string $from, string $to): StreamedResponse
    {
        $filename = "faktury-{$from}-{$to}.csv";

        return response()->streamDownload(function () use ($invoices) {
            $out = fopen('php://output', 'w');

            // BOM, aby Excel na Windows nerozbil diakritiku.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Číslo', 'Typ', 'Stav', 'Odberateľ', 'IČO', 'DIČ', 'IČ DPH',
                'Vystavené', 'Dodané', 'Splatné', 'Uhradené',
                'VS', 'KS', 'Základ', 'DPH', 'Sadzba', 'Celkom', 'Mena',
                'Prenesenie DP',
            ], ';');

            foreach ($invoices as $invoice) {
                $snapshot = $invoice->billing_snapshot ?? [];

                fputcsv($out, [
                    $invoice->number,
                    $invoice->type->shortLabel(),
                    $invoice->status->label(),
                    $snapshot['name'] ?? $invoice->organization?->name,
                    $snapshot['ico'] ?? null,
                    $snapshot['dic'] ?? null,
                    $snapshot['ic_dph'] ?? null,
                    $invoice->issued_at?->toDateString(),
                    $invoice->delivered_at?->toDateString(),
                    $invoice->due_at?->toDateString(),
                    $invoice->paid_at?->toDateString(),
                    $invoice->variable_symbol,
                    $invoice->constant_symbol,
                    number_format($invoice->subtotal_cents / 100, 2, ',', ''),
                    number_format($invoice->vat_cents / 100, 2, ',', ''),
                    number_format((float) $invoice->vat_rate, 2, ',', ''),
                    number_format($invoice->total_cents / 100, 2, ',', ''),
                    $invoice->currency,
                    $invoice->reverse_charge ? 'áno' : 'nie',
                ], ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @param \Illuminate\Support\Collection<int, Invoice> $invoices */
    protected function exportXml($invoices, string $from, string $to): StreamedResponse
    {
        $filename = "faktury-{$from}-{$to}.xml";

        return response()->streamDownload(function () use ($invoices, $from, $to) {
            $xml = new \XMLWriter;
            $xml->openURI('php://output');
            $xml->startDocument('1.0', 'UTF-8');
            $xml->setIndent(true);

            $xml->startElement('faktury');
            $xml->writeAttribute('od', $from);
            $xml->writeAttribute('do', $to);
            $xml->writeAttribute('vygenerovane', now()->toIso8601String());

            foreach ($invoices as $invoice) {
                $snapshot = $invoice->billing_snapshot ?? [];

                $xml->startElement('faktura');
                $xml->writeAttribute('typ', $invoice->type->value);

                $xml->writeElement('cislo', (string) $invoice->number);
                $xml->writeElement('stav', $invoice->status->value);
                $xml->writeElement('datum_vystavenia', (string) $invoice->issued_at?->toDateString());
                $xml->writeElement('datum_dodania', (string) $invoice->delivered_at?->toDateString());
                $xml->writeElement('datum_splatnosti', (string) $invoice->due_at?->toDateString());
                $xml->writeElement('variabilny_symbol', (string) $invoice->variable_symbol);
                $xml->writeElement('konstantny_symbol', (string) $invoice->constant_symbol);
                $xml->writeElement('mena', $invoice->currency);

                $xml->startElement('odberatel');
                $xml->writeElement('nazov', (string) ($snapshot['name'] ?? $invoice->organization?->name));
                $xml->writeElement('ico', (string) ($snapshot['ico'] ?? ''));
                $xml->writeElement('dic', (string) ($snapshot['dic'] ?? ''));
                $xml->writeElement('ic_dph', (string) ($snapshot['ic_dph'] ?? ''));
                $xml->writeElement('adresa', (string) ($snapshot['address']['line'] ?? ''));
                $xml->endElement();

                $xml->startElement('polozky');
                foreach ($invoice->items as $item) {
                    $xml->startElement('polozka');
                    $xml->writeElement('popis', $item->description);
                    $xml->writeElement('mnozstvo', (string) (float) $item->quantity);
                    $xml->writeElement('mj', $item->unit);
                    $xml->writeElement('cena_bez_dph', number_format($item->subtotal_cents / 100, 2, '.', ''));
                    $xml->writeElement('sadzba_dph', number_format((float) $item->vat_rate, 2, '.', ''));
                    $xml->writeElement('dph', number_format($item->vat_cents / 100, 2, '.', ''));
                    $xml->endElement();
                }
                $xml->endElement();

                $xml->startElement('sumar');
                $xml->writeElement('zaklad', number_format($invoice->subtotal_cents / 100, 2, '.', ''));
                $xml->writeElement('dph', number_format($invoice->vat_cents / 100, 2, '.', ''));
                $xml->writeElement('celkom', number_format($invoice->total_cents / 100, 2, '.', ''));
                $xml->writeElement('prenesenie_danovej_povinnosti', $invoice->reverse_charge ? '1' : '0');
                $xml->endElement();

                $xml->endElement();
            }

            $xml->endElement();
            $xml->endDocument();
            $xml->flush();
        }, $filename, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /* ===============================================================
     | Pomocné
     |===============================================================*/

    /**
     * Doménové výnimky (napr. „vystavený doklad sa nedá meniť“) sú chyby
     * obsluhy, nie chyby aplikácie – vracajú sa ako flash, nie ako 500.
     *
     * @param  \Closure(): RedirectResponse  $action
     */
    protected function guard(\Closure $action): RedirectResponse
    {
        try {
            return $action();
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
