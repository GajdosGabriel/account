<?php

namespace App\Models;

use App\Enums\InvoiceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Číselný rad dokladov.
 *
 * Číslo sa nikdy neodvodzuje z MAX(number) – pri dvoch súbežných
 * požiadavkách by vznikli duplicity. Poradie drží tento riadok
 * a inkrementuje sa v transakcii so zámkom (viď InvoiceNumberGenerator).
 */
class InvoiceNumberSeries extends Model
{
    use SoftDeletes;

    protected $table = 'invoice_number_series';

    protected $fillable = [
        'key', 'name', 'document_type', 'pattern', 'sequence_length',
        'reset_period', 'period_year', 'period_month', 'next_sequence', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => InvoiceType::class,
            'sequence_length' => 'integer',
            'period_year' => 'integer',
            'period_month' => 'integer',
            'next_sequence' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'number_series_id');
    }

    /**
     * Zloží číslo dokladu podľa vzoru.
     * Zástupné znaky: {YYYY} {YY} {MM} {SEQ}
     */
    public function format(int $sequence, \DateTimeInterface $date): string
    {
        return str_replace(
            ['{YYYY}', '{YY}', '{MM}', '{SEQ}'],
            [
                $date->format('Y'),
                $date->format('y'),
                $date->format('m'),
                str_pad((string) $sequence, $this->sequence_length, '0', STR_PAD_LEFT),
            ],
            $this->pattern,
        );
    }

    /** Ukážka ďalšieho čísla – pre nastavenia v UI. */
    public function preview(): string
    {
        return $this->format($this->next_sequence, now());
    }
}
