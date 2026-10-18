<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * E-mailová schránka projektu: odkiaľ sa posiela a odkiaľ sa čítajú odpovede.
 *
 * Projekt môže mať viac schránok (kontakt@, obchod@…). Pri odoslaní bez
 * výberu sa použije predvolená. Heslá sú v databáze šifrované.
 */
class Mailbox extends Model
{
    protected $fillable = [
        'product_id', 'name', 'from_address', 'from_name',
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password',
        'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password', 'imap_folder',
        'is_default', 'is_active',
    ];

    protected $hidden = ['smtp_password', 'imap_password'];

    protected function casts(): array
    {
        return [
            'smtp_password' => 'encrypted',
            'imap_password' => 'encrypted',
            'imap_checked_at' => 'datetime',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** @return HasMany<Conversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function readsInbound(): bool
    {
        return $this->is_active && filled($this->imap_host) && filled($this->imap_username);
    }

    public function hasOwnSmtp(): bool
    {
        return filled($this->smtp_host);
    }

    /** Doména pre Message-ID – podľa adresy odosielateľa. */
    public function domain(): string
    {
        $at = strrchr($this->from_address, '@');

        return strtolower($at === false ? 'localhost' : substr($at, 1));
    }

    /** Predvolená schránka projektu (alebo hocijaká aktívna, ak predvolená nie je). */
    public static function defaultFor(Product $product): ?self
    {
        return static::query()
            ->where('product_id', $product->id)
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }
}
