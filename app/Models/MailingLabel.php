<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Ručný štítok kontaktov v Accounte (napr. „Samospráva“, „Import veľtrh 2026“).
 *
 * Kontakt si drží len kľúč v `manual_tags`; názov a farba sú tu, takže
 * premenovanie sa prejaví všade naraz. Kľúč sa pri premenovaní nemení –
 * kampane, ktoré naň mieria, ostanú platné.
 */
class MailingLabel extends Model
{
    public const COLORS = ['indigo', 'sky', 'emerald', 'amber', 'orange', 'rose', 'violet', 'slate'];

    public const MAX_PER_CONTACT = 20;

    protected $fillable = ['product_id', 'key', 'name', 'color'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Kľúče štítkov podľa zadaných názvov alebo kľúčov. Štítok sa nájde
     * podľa názvu (bez ohľadu na veľkosť písmen) alebo kľúča; chýbajúci
     * sa vytvorí, ak `$create`.
     *
     * @param  string|list<string>|null  $input  „Samospráva, nový klient“ alebo zoznam
     * @return list<string>
     */
    public static function resolve(int $productId, string|array|null $input, bool $create = true): array
    {
        $names = collect(is_array($input) ? $input : preg_split('/[,;\r\n]+/', (string) $input))
            ->map(fn ($name) => Str::limit(trim(preg_replace('/\s+/u', ' ', (string) $name)), 60, ''))
            ->filter(fn ($name) => Str::slug($name) !== '')
            ->unique(fn ($name) => Str::slug($name));

        if ($names->isEmpty()) {
            return [];
        }

        $existing = self::query()->where('product_id', $productId)->get();

        return $names->map(function (string $name) use ($existing, $productId, $create) {
            $key = Str::limit(Str::slug($name), 60, '');
            $label = $existing->first(fn (self $l) => mb_strtolower($l->name) === mb_strtolower($name))
                ?? $existing->firstWhere('key', $key);

            if (! $label && $create) {
                $label = self::create(['product_id' => $productId, 'key' => $key, 'name' => $name]);
                $existing->push($label);
            }

            return $label?->key;
        })->filter()->unique()->sort()->values()->all();
    }
}
