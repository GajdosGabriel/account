<?php

namespace App\Services\Invoicing;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Support\Carbon;

/**
 * Čísla o fakturácii pre prehľad prevádzkovateľa.
 *
 * Tri veci, ktoré chce človek vidieť pri rannej káve: čo sa tento mesiac
 * vyfakturovalo, čo z toho ešte nemáme na účte, a čo príde najbližšie.
 *
 * Dve rozhodnutia, ktoré sa oplatí poznať:
 *
 *   - Zálohové faktúry sa do tržieb nerátajú. Zálohová sa neskôr mení
 *     na riadnu a súčet oboch by ten istý obchod započítal dvakrát.
 *   - Dobropis má zápornú sumu už v databáze (dobropisuje sa zápornou
 *     množstvom), takže sa jednoducho pripočíta a tržbu zníži sám.
 *
 * Zoskupovanie po mesiacoch je v PHP, nie v SQL: `strftime` je sqlite,
 * `date_format` mysql, a prehľad nemá byť viazaný na jeden ovládač.
 */
class InvoiceStatistics
{
    /** Stavy dokladu, ktorý sa už rátal do tržieb. */
    private const COUNTED = [
        InvoiceStatus::Issued,
        InvoiceStatus::Sent,
        InvoiceStatus::PartiallyPaid,
        InvoiceStatus::Paid,
        InvoiceStatus::Overdue,
    ];

    /**
     * Tento mesiac a stav pohľadávok.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $month = Invoice::query()
            ->whereIn('type', [InvoiceType::Invoice->value, InvoiceType::CreditNote->value])
            ->whereIn('status', $this->countedStatuses())
            ->whereBetween('issued_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->get(['type', 'total_cents']);

        $outstanding = Invoice::query()->unpaid()->get(['total_cents', 'paid_cents']);
        $overdue = Invoice::query()->overdue()->get(['total_cents', 'paid_cents']);

        return [
            'invoiced_month' => [
                'count' => $month->count(),
                'cents' => (int) $month->sum('total_cents'),
            ],
            'paid_month' => $this->paidThisMonth(),
            'outstanding' => [
                'count' => $outstanding->count(),
                'cents' => $this->remaining($outstanding),
            ],
            'overdue' => [
                'count' => $overdue->count(),
                'cents' => $this->remaining($overdue),
            ],
            'drafts' => Invoice::query()->where('status', InvoiceStatus::Draft->value)->count(),
            'avg_days_to_pay' => $this->averageDaysToPay(),
        ];
    }

    /**
     * Čo pritečie v najbližších dňoch.
     *
     * Prognóza stojí na dvoch veciach, ktoré už niekde sú – na splatných
     * pohľadávkach a na predplatných, ktorým končí obdobie. Nič sa
     * neextrapoluje: čo tu je, to sa dá skontrolovať v zozname.
     *
     * @return array<string, mixed>
     */
    public function forecast(int $days = 30): array
    {
        $until = now()->addDays($days);

        $due = Invoice::query()->unpaid()
            ->whereDate('due_at', '>=', today())
            ->whereDate('due_at', '<=', $until)
            ->get(['total_cents', 'paid_cents']);

        $renewals = Subscription::query()
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Trialing->value,
                SubscriptionStatus::PastDue->value,
            ])
            ->whereNotNull('current_period_end')
            ->whereDate('current_period_end', '>=', today())
            ->whereDate('current_period_end', '<=', $until)
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->get(['plans.price_cents', 'subscriptions.quantity']);

        $dueCents = $this->remaining($due);
        $renewalCents = (int) $renewals->sum(fn ($row) => $row->price_cents * $row->quantity);

        // Po splatnosti sa do očakávaného príjmu neráta – termín už
        // ubehol, takže to nie je prognóza, ale riziko.
        $overdue = Invoice::query()->overdue()->get(['total_cents', 'paid_cents']);

        return [
            'days' => $days,
            'until' => $until->toDateString(),
            'due' => ['count' => $due->count(), 'cents' => $dueCents],
            'renewals' => ['count' => $renewals->count(), 'cents' => $renewalCents],
            'at_risk' => ['count' => $overdue->count(), 'cents' => $this->remaining($overdue)],
            'total_cents' => $dueCents + $renewalCents,
        ];
    }

    /**
     * Vývoj fakturácie po mesiacoch.
     *
     * @return array<int, array<string, mixed>>
     */
    public function months(int $count = 6): array
    {
        $from = now()->startOfMonth()->subMonths($count - 1);

        $invoices = Invoice::query()
            ->whereIn('type', [InvoiceType::Invoice->value, InvoiceType::CreditNote->value])
            ->whereIn('status', $this->countedStatuses())
            ->where('issued_at', '>=', $from)
            ->get(['issued_at', 'paid_at', 'total_cents', 'paid_cents']);

        $months = [];

        for ($i = 0; $i < $count; $i++) {
            $month = $from->copy()->addMonths($i);
            $key = $month->format('Y-m');

            $months[$key] = [
                'key' => $key,
                'label' => $month->format('m/Y'),
                'invoiced_cents' => 0,
                'paid_cents' => 0,
            ];
        }

        foreach ($invoices as $invoice) {
            $issued = $invoice->issued_at?->format('Y-m');

            if ($issued !== null && isset($months[$issued])) {
                $months[$issued]['invoiced_cents'] += $invoice->total_cents;
            }

            $paid = $invoice->paid_at?->format('Y-m');

            if ($paid !== null && isset($months[$paid])) {
                $months[$paid]['paid_cents'] += $invoice->paid_cents;
            }
        }

        return array_values($months);
    }

    /* ---------------------------------------------------------------
     | Pomocné
     |---------------------------------------------------------------*/

    /**
     * Uhradené tento mesiac.
     *
     * Evidencia nemá knihu platieb, len `paid_at` na doklade – čiastočná
     * úhrada preto nemá vlastný dátum a do mesiaca sa dostane až vtedy,
     * keď je doklad uhradený celý. Je to menej, než koľko naozaj pritieklo,
     * nikdy nie viac.
     *
     * @return array<string, int>
     */
    protected function paidThisMonth(): array
    {
        $paid = Invoice::query()
            ->whereIn('type', [InvoiceType::Invoice->value, InvoiceType::CreditNote->value])
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->get(['paid_cents']);

        return ['count' => $paid->count(), 'cents' => (int) $paid->sum('paid_cents')];
    }

    /**
     * Priemerný počet dní od vystavenia po úhradu za posledný štvrťrok.
     *
     * Ráta sa v PHP – rozdiel dvoch dátumov píše každý ovládač inak
     * a kvôli jednému číslu to nestojí za vetvenie podľa databázy.
     */
    protected function averageDaysToPay(): ?int
    {
        $paid = Invoice::query()
            ->ofType(InvoiceType::Invoice)
            ->whereNotNull('paid_at')
            ->whereNotNull('issued_at')
            ->where('paid_at', '>=', now()->subDays(90))
            ->get(['issued_at', 'paid_at']);

        if ($paid->isEmpty()) {
            return null;
        }

        $days = $paid->map(fn (Invoice $invoice) => Carbon::parse($invoice->issued_at)
            ->diffInDays(Carbon::parse($invoice->paid_at)));

        return (int) round($days->avg());
    }

    /** @param \Illuminate\Support\Collection<int, Invoice> $invoices */
    protected function remaining($invoices): int
    {
        return (int) $invoices->sum(fn (Invoice $invoice) => $invoice->total_cents - $invoice->paid_cents);
    }

    /** @return array<int, string> */
    protected function countedStatuses(): array
    {
        return array_map(fn (InvoiceStatus $status) => $status->value, self::COUNTED);
    }
}
