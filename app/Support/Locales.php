<?php

namespace App\Support;

/**
 * Jazyky rozhrania.
 *
 * Zoznam podporovaných jazykov je v config/accounts.php – tá istá
 * hodnota riadi aj jazyk API odpovedí. Názvy sú zámerne v danom jazyku
 * („Deutsch“, nie „nemčina“): kto hľadá svoj jazyk, hľadá ho v ňom.
 */
class Locales
{
    /** @var array<string, string> */
    private const NATIVE = [
        'sk' => 'Slovenčina',
        'cs' => 'Čeština',
        'de' => 'Deutsch',
        'en' => 'English',
    ];

    /** @return array<int, string> */
    public static function supported(): array
    {
        return config('accounts.locales', ['sk']);
    }

    public static function supports(string $locale): bool
    {
        return in_array($locale, static::supported(), true);
    }

    public static function name(string $locale): string
    {
        return static::NATIVE[$locale] ?? strtoupper($locale);
    }

    /**
     * Voľby do prepínača v navigácii.
     *
     * @return array<int, array<string, string>>
     */
    public static function options(): array
    {
        return array_map(fn (string $locale) => [
            'value' => $locale,
            'label' => static::name($locale),
            'short' => strtoupper($locale),
        ], static::supported());
    }
}
