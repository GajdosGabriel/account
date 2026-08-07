<?php

namespace App\Enums;

enum LegalForm: string
{
    case Sro = 'sro';
    case Zivnost = 'zivnost';
    case As = 'as';
    case Ks = 'ks';
    case Vos = 'vos';
    case Druzstvo = 'druzstvo';
    case Nezisk = 'nezisk';
    case Fyzicka = 'fyzicka';
    case Ine = 'ine';

    public function label(): string
    {
        return match ($this) {
            self::Sro => 'Spoločnosť s ručením obmedzeným',
            self::Zivnost => 'Živnosť',
            self::As => 'Akciová spoločnosť',
            self::Ks => 'Komanditná spoločnosť',
            self::Vos => 'Verejná obchodná spoločnosť',
            self::Druzstvo => 'Družstvo',
            self::Nezisk => 'Nezisková organizácia',
            self::Fyzicka => 'Fyzická osoba',
            self::Ine => 'Iné',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Sro => 's.r.o.',
            self::Zivnost => 'živnosť',
            self::As => 'a.s.',
            self::Ks => 'k.s.',
            self::Vos => 'v.o.s.',
            self::Druzstvo => 'družstvo',
            self::Nezisk => 'n.o.',
            self::Fyzicka => 'FO',
            self::Ine => '—',
        };
    }

    /**
     * Zapisuje sa firma do obchodného registra, alebo do živnostenského?
     * Podľa toho sa v UI pýtame na súd a vložku, alebo na číslo živnosti.
     */
    public function usesCommercialRegister(): bool
    {
        return in_array($this, [self::Sro, self::As, self::Ks, self::Vos, self::Druzstvo], true);
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $form) => ['value' => $form->value, 'label' => $form->label()],
            self::cases(),
        );
    }
}
