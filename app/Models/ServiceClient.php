<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ServiceClient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id', 'name', 'token_prefix', 'token_hash', 'abilities', 'expires_at', 'revoked_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Vytvori novy token. Plain hodnotu vraciame len raz - dalej
     * je v DB iba SHA-256 hash.
     *
     * @param  array<int, string>  $abilities
     * @return array{0: self, 1: string}
     */
    public static function issue(Product $product, string $name, array $abilities = ['organizations:read', 'organizations:write', 'entitlements:read', 'usage:write']): array
    {
        $plain = 'acc_'.Str::random(48);

        $client = static::create([
            'product_id' => $product->id,
            'name' => $name,
            'token_prefix' => substr($plain, 0, 12),
            'token_hash' => hash('sha256', $plain),
            'abilities' => $abilities,
        ]);

        return [$client, $plain];
    }

    public static function findByPlainToken(string $plain): ?self
    {
        return static::query()
            ->where('token_hash', hash('sha256', $plain))
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
    }

    public function hasAbility(string $ability): bool
    {
        $abilities = $this->abilities ?? [];

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
