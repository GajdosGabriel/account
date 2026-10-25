<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Odosielateľ, ktorého pošta v danom projekte ide rovno do Spamu.
 *
 * Platí len v projekte – rovnako ako `MailSuppression`. Odpovede vo
 * vláknach priradených konverzácií blok neovplyvní.
 */
class InboundBlock extends Model
{
    protected $fillable = ['product_id', 'email', 'created_by_user_id'];

    protected static function booted(): void
    {
        static::saving(function (self $block) {
            $block->email = strtolower(trim($block->email));
        });
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function blocks(int $productId, string $email): bool
    {
        return static::query()
            ->where('product_id', $productId)
            ->where('email', strtolower(trim($email)))
            ->exists();
    }
}
