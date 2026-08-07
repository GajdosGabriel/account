<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jedna položka katalógu funkcií produktu.
 *
 *   type = 'flag'   → hodnota je true/false      (napr. export)
 *   type = 'limit'  → hodnota je číslo alebo null (null = neobmedzene)
 *
 * `metric` páruje limit so spotrebou, ktorú projekt hlási:
 * feature `max_records` + metric `records`.
 */
class ProductFeature extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFeatureFactory> */
    use HasFactory, SoftDeletes;

    public const TYPE_FLAG = 'flag';

    public const TYPE_LIMIT = 'limit';

    protected $fillable = [
        'product_id', 'key', 'name', 'type', 'unit', 'metric',
        'default_value', 'description', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['default_value' => 'array'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isLimit(): bool
    {
        return $this->type === self::TYPE_LIMIT;
    }

    public function isFlag(): bool
    {
        return $this->type === self::TYPE_FLAG;
    }

    /** Hodnota, ktorá platí, keď ju plán neuvádza. */
    public function defaultValue(): mixed
    {
        // `default_value` je JSON, aby uniesol aj null (= neobmedzene)
        $stored = $this->default_value;

        if (is_array($stored) && array_key_exists('value', $stored)) {
            return $stored['value'];
        }

        return $this->isFlag() ? false : 0;
    }

    /** Prevedie surovú hodnotu z formulára na správny typ. */
    public function castValue(mixed $value): mixed
    {
        if ($this->isFlag()) {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        // prazdny retazec z formulara = neobmedzene
        if ($value === null || $value === '' || $value === 'unlimited') {
            return null;
        }

        return (int) $value;
    }

    public function formatValue(mixed $value): string
    {
        if ($this->isFlag()) {
            return $value ? 'áno' : 'nie';
        }

        if ($value === null) {
            return 'neobmedzene';
        }

        return trim($value.' '.($this->unit ?? ''));
    }
}
