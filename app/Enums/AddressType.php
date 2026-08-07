<?php

namespace App\Enums;

enum AddressType: string
{
    case Mailing = 'mailing';
    case Delivery = 'delivery';
    case Branch = 'branch';

    public function label(): string
    {
        return match ($this) {
            self::Mailing => 'Adresa na zasielanie pošty',
            self::Delivery => 'Dodacia adresa',
            self::Branch => 'Prevádzkareň',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Mailing => 'Pošta',
            self::Delivery => 'Doručenie',
            self::Branch => 'Prevádzka',
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
