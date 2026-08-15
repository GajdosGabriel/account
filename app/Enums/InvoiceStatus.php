<?php

namespace App\Enums;

/**
 * Životný cyklus dokladu.
 *
 *   draft ──> issued ──> sent ──┬──> partially_paid ──> paid
 *     │          │        │     └──> paid
 *     │          └────────┴──> overdue ──> paid
 *     │
 *     └──> (zmazanie)          issued/sent/overdue ──> cancelled
 *
 * Vystavený doklad sa už nesmie mazať ani meniť – opravuje sa dobropisom.
 * Toto je zákonná požiadavka, nie rozmar; drží ju InvoicePolicy.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Sent = 'sent';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('enums.invoice_status.'.$this->value);
    }

    /** Farba pre StatusBadge vo frontende. */
    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Issued => 'sky',
            self::Sent => 'indigo',
            self::PartiallyPaid => 'amber',
            self::Paid => 'emerald',
            self::Overdue => 'rose',
            self::Cancelled => 'slate',
        };
    }

    /** Doklad ešte nemá pridelené číslo a smie sa ľubovoľne meniť. */
    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    /** Doklad je vystavený – obsah je zamknutý. */
    public function isLocked(): bool
    {
        return $this !== self::Draft;
    }

    public function isSettled(): bool
    {
        return in_array($this, [self::Paid, self::Cancelled], true);
    }

    /** Čaká sa na peniaze. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Issued, self::Sent, self::PartiallyPaid, self::Overdue], true);
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $status) => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}
