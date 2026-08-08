<?php

namespace App\Enums;

/**
 * Kto platí – firma alebo súkromná osoba.
 *
 * Nie každý zákazník je podnikateľ. Občan bez IČO má nárok na faktúru
 * rovnako ako s.r.o., len sa od neho nedajú pýtať údaje, ktoré nemá:
 * IČO, DIČ, zápis v obchodnom registri. Dovtedy sa preto nedal uložiť
 * bez toho, aby polia zostali prázdne a firma navždy „nekompletná“.
 */
enum SubjectType: string
{
    case Company = 'company';
    case Person = 'person';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Organizácia',
            self::Person => 'Súkromná osoba',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Company => 'Firma, živnostník alebo nezisková organizácia s IČO.',
            self::Person => 'Občan bez IČO. Stačí meno a adresa.',
        };
    }

    public function isPerson(): bool
    {
        return $this === self::Person;
    }

    /** Názov poľa pre meno – u osoby je to meno človeka, nie firmy. */
    public function nameLabel(): string
    {
        return $this->isPerson() ? 'Meno a priezvisko' : 'Názov firmy';
    }

    /** @return array<int, array{value: string, label: string, description: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
            ],
            self::cases(),
        );
    }
}
