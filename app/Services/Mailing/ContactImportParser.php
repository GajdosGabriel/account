<?php

namespace App\Services\Mailing;

use Illuminate\Support\Str;

/**
 * Kontakty z vloženého textu alebo súboru CSV.
 *
 * Riadok „meno <adresa>“, len adresa alebo riadok z CSV („adresa;meno“,
 * „meno,adresa“ …). CSV s hlavičkou (aj export kontaktov) sa číta podľa
 * stĺpcov vrátane stĺpca so štítkami; riadky bez adresy sa preskočia.
 */
class ContactImportParser
{
    /**
     * Adresy z vloženého textu alebo CSV: [adresa => [meno|null, názvy štítkov]]
     * bez duplicít a počet neplatných adries.
     *
     * @return array{0: array<string, array{name: ?string, labels: list<string>}>, 1: int}
     */
    public static function parse(string $text): array
    {
        $rows = [];
        $skipped = 0;
        $columns = null;

        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim(preg_replace('/^\x{FEFF}/u', '', $line));

            // Riadok bez zavináča (hlavička CSV, prázdny riadok) nie je kontakt.
            if (! str_contains($line, '@')) {
                $columns ??= self::csvHeader($line);

                continue;
            }

            if ($columns && ($row = self::csvRow($line, $columns))) {
                [$email, $name, $labels] = $row;
                $email = strtolower(trim($email, " \t\"'<>"));

                if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
                    $skipped++;

                    continue;
                }

                $rows[$email] ??= ['name' => $name ? Str::limit($name, 255, '') : null, 'labels' => $labels];

                continue;
            }

            $fields = array_values(array_filter(array_map(
                fn ($field) => trim($field, " \t\"'"),
                preg_split('/[,;\t]+/', $line),
            ), fn ($field) => $field !== ''));
            $emails = [];
            $names = [];

            foreach ($fields as $field) {
                // „Jana Nová <jana@firma.sk>“
                if (preg_match('/^(.*?)<\s*([^<>\s]+@[^<>\s]+)\s*>$/u', $field, $m)) {
                    $emails[] = $m[2];
                    $names[] = trim($m[1], " \t\"'");
                } elseif (str_contains($field, '@')) {
                    $emails[] = $field;
                } else {
                    $names[] = $field;
                }
            }

            // Jedna adresa v riadku: meno je prvé ďalšie pole; viac adries je len zoznam.
            $name = count($emails) === 1 ? (collect($names)->first(fn ($n) => $n !== '' && ! is_numeric($n))) : null;

            foreach ($emails as $email) {
                $email = strtolower(trim($email, " \t\"'<>"));

                if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
                    $skipped++;

                    continue;
                }

                $rows[$email] ??= ['name' => $name ? Str::limit($name, 255, '') : null, 'labels' => []];
            }
        }

        return [$rows, $skipped];
    }

    /**
     * Hlavička CSV so stĺpcom adresy: oddeľovač a poradie stĺpcov adresa,
     * meno a štítky. Bez stĺpca adresy null – riadky sa čítajú voľne.
     *
     * @return array{delimiter: string, email: int, name: ?int, labels: ?int}|null
     */
    private static function csvHeader(string $line): ?array
    {
        if ($line === '') {
            return null;
        }

        $delimiter = collect([';', "\t", ','])->sortByDesc(fn ($d) => substr_count($line, $d))->first();
        $keys = array_map(fn ($field) => Str::slug((string) $field), str_getcsv($line, $delimiter, '"', ''));
        $find = function (array $names) use ($keys): ?int {
            $index = array_search(true, array_map(fn ($key) => in_array($key, $names, true), $keys), true);

            return $index === false ? null : $index;
        };
        $email = $find(['email', 'e-mail', 'mail', 'adresa', 'emailova-adresa']);

        if ($email === null) {
            return null;
        }

        return [
            'delimiter' => $delimiter,
            'email' => $email,
            'name' => $find(['meno', 'name', 'meno-a-priezvisko', 'nazov', 'kontakt']),
            'labels' => $find(['stitky', 'stitok', 'labels', 'label', 'tags']),
        ];
    }

    /**
     * Riadok CSV podľa hlavičky: [adresa, meno, názvy štítkov]. Null, keď
     * v stĺpci adresy nie je platná adresa – riadok sa potom číta voľne.
     *
     * @param  array{delimiter: string, email: int, name: ?int, labels: ?int}  $columns
     * @return array{0: string, 1: ?string, 2: list<string>}|null
     */
    private static function csvRow(string $line, array $columns): ?array
    {
        $fields = array_map(fn ($field) => trim((string) $field, " \t\"'"), str_getcsv($line, $columns['delimiter'], '"', ''));
        $email = $fields[$columns['email']] ?? '';

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $name = $columns['name'] !== null ? ($fields[$columns['name']] ?? '') : '';
        $labels = $columns['labels'] !== null ? preg_split('/[,;|]+/', $fields[$columns['labels']] ?? '') : [];

        return [
            $email,
            $name !== '' ? $name : null,
            array_values(array_filter(array_map('trim', $labels), fn ($label) => $label !== '')),
        ];
    }
}
