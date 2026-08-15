<?php

namespace App\Enums;

/**
 * Vzťah firmy k DPH. Určuje, čo sa píše na faktúru.
 */
enum VatMode: string
{
    case NonPayer = 'non_payer';
    case Payer = 'payer';
    case Registered7 = 'reg_7';
    case Registered7a = 'reg_7a';

    public function label(): string
    {
        return __('enums.vat_mode.'.$this->value.'.label');
    }

    public function description(): string
    {
        return __('enums.vat_mode.'.$this->value.'.description');
    }

    /** Má firma IČ DPH? Pri § 7 a § 7a áno, hoci nie je platiteľ. */
    public function hasVatNumber(): bool
    {
        return $this !== self::NonPayer;
    }

    public function isPayer(): bool
    {
        return $this === self::Payer;
    }

    /** @return array<int, array{value: string, label: string, description: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $mode) => [
                'value' => $mode->value,
                'label' => $mode->label(),
                'description' => $mode->description(),
            ],
            self::cases(),
        );
    }
}
