<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Šablóna odpovede v inboxe.
 *
 * - bez `product_id` platí pre všetky projekty,
 * - s `user_id` je súkromná (vidí ju len autor), inak ju zdieľa celý tím,
 * - `shortcut` sa v odpovedi rozbalí zápisom „/skratka“ + Tab.
 *
 * Premenné ({first_name}, {agent}…) dosadzuje až frontend pri vložení,
 * operátor tak text ešte vidí a môže upraviť pred odoslaním.
 */
class ReplyTemplate extends Model
{
    /** Premenné, ktoré šablóna pozná – poradie je poradie v nápovede. */
    public const VARIABLES = [
        'first_name', 'name', 'email', 'organization', 'product', 'subject', 'ref', 'agent', 'agent_first_name', 'cursor',
    ];

    protected $fillable = ['product_id', 'user_id', 'name', 'shortcut', 'subject', 'body'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** Zdieľané šablóny a súkromné šablóny daného operátora. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id));
    }

    /** Šablóny použiteľné v projekte – jeho vlastné aj spoločné. */
    public function scopeForProduct(Builder $query, ?int $productId): void
    {
        $query->where(fn ($q) => $q->whereNull('product_id')
            ->when($productId, fn ($w) => $w->orWhere('product_id', $productId)));
    }

    public function isPrivate(): bool
    {
        return $this->user_id !== null;
    }

    public function canBeEditedBy(User $user): bool
    {
        return $this->user_id === null || $this->user_id === $user->id;
    }

    /** Použitie nemení „naposledy upravená“ – updated_at ostáva. */
    public function markUsed(): void
    {
        $this->timestamps = false;
        $this->forceFill([
            'uses_count' => $this->uses_count + 1,
            'last_used_at' => now(),
        ])->save();
        $this->timestamps = true;
    }
}
