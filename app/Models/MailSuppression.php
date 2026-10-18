<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Adresa, na ktorú sa v danom projekte neposiela (trvalý bounce, odhlásenie).
 *
 * Blokovanie platí len v projekte – odhlásenie z noviniek jedného projektu
 * neznamená, že človek nechce odpoveď na objednávku v inom.
 */
class MailSuppression extends Model
{
    public const HARD_BOUNCE = 'hard_bounce';

    public const UNSUBSCRIBED = 'unsubscribed';

    public const MANUAL = 'manual';

    protected $fillable = ['product_id', 'email', 'reason', 'bounce_count', 'note'];

    protected static function booted(): void
    {
        static::saving(function (self $suppression) {
            $suppression->email = strtolower(trim($suppression->email));
        });
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function blocks(Product $product, string $email): bool
    {
        return static::query()
            ->where('product_id', $product->id)
            ->where('email', strtolower(trim($email)))
            ->exists();
    }
}
