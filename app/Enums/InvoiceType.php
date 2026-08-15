<?php

namespace App\Enums;

/**
 * Typy dokladov, ktoré sa v SR bežne vystavujú.
 */
enum InvoiceType: string
{
    /** Riadna faktúra – daňový doklad. */
    case Invoice = 'invoice';

    /** Zálohová (proforma) faktúra – nie je daňový doklad, len výzva na úhradu. */
    case Proforma = 'proforma';

    /** Dobropis – opravná faktúra so zápornými sumami. */
    case CreditNote = 'credit_note';

    public function label(): string
    {
        return __('enums.invoice_type.'.$this->value);
    }

    public function shortLabel(): string
    {
        return __('enums.invoice_type_short.'.$this->value);
    }

    /** Zálohová faktúra nevstupuje do priznania DPH ani do výnosov. */
    public function isTaxDocument(): bool
    {
        return $this !== self::Proforma;
    }

    /** Dobropis má všetky sumy záporné. */
    public function sign(): int
    {
        return $this === self::CreditNote ? -1 : 1;
    }

    public function defaultSeriesKey(): string
    {
        return match ($this) {
            self::Invoice => 'faktura',
            self::Proforma => 'zaloha',
            self::CreditNote => 'dobropis',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
