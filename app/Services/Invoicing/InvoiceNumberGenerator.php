<?php

namespace App\Services\Invoicing;

use App\Enums\InvoiceType;
use App\Models\InvoiceNumberSeries;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Prideľovanie čísel dokladov.
 *
 * Zákon o účtovníctve chce neprerušený vzostupný rad. Preto:
 *
 *  - číslo sa priraďuje až pri VYSTAVENÍ, nie pri vytvorení konceptu
 *    (koncept sa dá zmazať, číslo by v rade chýbalo),
 *  - poradie sa berie zo zamknutého riadku číselného radu, nie z MAX(),
 *  - rad sa na začiatku roka automaticky reštartuje na 1.
 */
class InvoiceNumberGenerator
{
    /**
     * Vráti ďalšie číslo a rovno posunie rad.
     * Musí bežať vo vnútri transakcie volajúceho, aby sa pri zlyhaní
     * vystavenia číslo nespotrebovalo.
     *
     * @return array{series: InvoiceNumberSeries, sequence: int, number: string}
     */
    public function next(InvoiceType $type, ?Carbon $date = null, ?InvoiceNumberSeries $series = null): array
    {
        $date ??= Carbon::today();

        return DB::transaction(function () use ($type, $date, $series) {
            $series = InvoiceNumberSeries::query()
                ->when($series, fn ($q) => $q->whereKey($series->id))
                ->when(! $series, fn ($q) => $q
                    ->where('document_type', $type->value)
                    ->orderByDesc('is_default'))
                ->lockForUpdate()
                ->first();

            if (! $series) {
                throw new RuntimeException(
                    "Pre typ dokladu „{$type->shortLabel()}“ nie je založený číselný rad."
                );
            }

            $this->rollPeriod($series, $date);

            $sequence = $series->next_sequence;
            $number = $series->format($sequence, $date);

            $series->forceFill(['next_sequence' => $sequence + 1])->save();

            return ['series' => $series, 'sequence' => $sequence, 'number' => $number];
        });
    }

    /**
     * Nové účtovné obdobie => rad začína znova od jednotky.
     * Robí sa lenivo pri prvom doklade v novom roku – žiadny cron.
     */
    protected function rollPeriod(InvoiceNumberSeries $series, Carbon $date): void
    {
        if ($series->reset_period === 'never') {
            return;
        }

        $year = (int) $date->format('Y');
        $month = $series->reset_period === 'month' ? (int) $date->format('n') : null;

        $changed = $series->period_year !== $year
            || ($series->reset_period === 'month' && $series->period_month !== $month);

        if (! $changed) {
            return;
        }

        // Prvé použitie radu – len si zapíšeme obdobie, sekvenciu nechávame.
        $sequence = $series->period_year === null ? $series->next_sequence : 1;

        $series->forceFill([
            'period_year' => $year,
            'period_month' => $month,
            'next_sequence' => $sequence,
        ])->save();
    }

    /** Náhľad ďalšieho čísla bez toho, aby sa spotrebovalo. */
    public function preview(InvoiceType $type, ?Carbon $date = null): ?string
    {
        $series = InvoiceNumberSeries::where('document_type', $type->value)
            ->orderByDesc('is_default')
            ->first();

        return $series?->format($series->next_sequence, $date ?? Carbon::today());
    }

    /**
     * Variabilný symbol z čísla dokladu.
     * Banky akceptujú maximálne 10 číslic – nechávame poslednú desiatku.
     */
    public function variableSymbol(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?: '';

        return substr($digits, -10) ?: (string) random_int(1000000, 9999999);
    }
}
