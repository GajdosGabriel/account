<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Transfer = 'transfer';
    case Card = 'card';
    case DirectDebit = 'direct_debit';
    case Cash = 'cash';
    case Cod = 'cod';

    public function label(): string
    {
        return match ($this) {
            self::Transfer => 'Prevodom na účet',
            self::Card => 'Platobnou kartou',
            self::DirectDebit => 'Inkasom',
            self::Cash => 'V hotovosti',
            self::Cod => 'Dobierkou',
        };
    }

    /** Pri týchto spôsoboch má zmysel tlačiť QR kód a bankové údaje. */
    public function needsBankDetails(): bool
    {
        return $this === self::Transfer;
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $method) => ['value' => $method->value, 'label' => $method->label()],
            self::cases(),
        );
    }
}
