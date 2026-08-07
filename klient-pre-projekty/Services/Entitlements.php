<?php

namespace App\Support;

/**
 * Odpoveď Accountu o tom, čo firma smie.
 *
 * Konvencia limitov: null = neobmedzene. Nikdy nie -1 ani 0 –
 * pri tých by porovnanie vyhodnotilo nesprávne.
 */
class Entitlements
{
    /**
     * @param  array<string, mixed>  $data
     * @param  bool  $stale  hodnota pochádza z obdobia výpadku Accountu
     */
    public function __construct(
        protected array $data,
        public readonly bool $stale = false,
    ) {}

    /** Použije sa, keď Account nikdy neodpovedal – radšej pustiť dnu. */
    public static function failOpen(): self
    {
        return new self([
            'status' => 'unknown',
            'access' => true,
            'read_only' => false,
            'features' => [],
            'usage' => [],
            'over_limit' => [],
        ], stale: true);
    }

    public function status(): string
    {
        return $this->data['status'] ?? 'unknown';
    }

    public function planName(): ?string
    {
        return $this->data['plan_name'] ?? null;
    }

    /** Smie firma v aplikácii vôbec pracovať? */
    public function allowsAccess(): bool
    {
        return (bool) ($this->data['access'] ?? false);
    }

    /** Čítanie áno, zápis nie – zobraz výzvu na zaplatenie. */
    public function isReadOnly(): bool
    {
        return (bool) ($this->data['read_only'] ?? false);
    }

    /** Úplne zamknuté – ani čítanie. */
    public function isLocked(): bool
    {
        return ! $this->allowsAccess() && ! $this->isReadOnly();
    }

    /** Hodnota funkcie: bool pri prepínači, int|null pri limite. */
    public function feature(string $key, mixed $default = null): mixed
    {
        return $this->data['features'][$key] ?? $default;
    }

    /** Je prepínač zapnutý? */
    public function can(string $feature): bool
    {
        return (bool) $this->feature($feature, false);
    }

    public function isUnlimited(string $feature): bool
    {
        return array_key_exists($feature, $this->data['features'] ?? [])
            && $this->data['features'][$feature] === null;
    }

    /**
     * Prekročil by daný počet limit?
     *
     *   if ($ent->exceeded('max_records', Zaznam::count())) { ... }
     *
     * Vracia false pri neobmedzenom limite aj pri neznámej funkcii –
     * neznáma funkcia nesmie zablokovať aplikáciu.
     */
    public function exceeded(string $feature, int $current, int $adding = 0): bool
    {
        $limit = $this->feature($feature);

        if ($limit === null) {
            return false;
        }

        return ($current + $adding) > (int) $limit;
    }

    /** Koľko ešte zostáva, null = neobmedzene. */
    public function remaining(string $feature, int $current): ?int
    {
        $limit = $this->feature($feature);

        return $limit === null ? null : max(0, (int) $limit - $current);
    }

    /** Funkcie, ktoré sú aktuálne nad limitom (napr. po znížení plánu). */
    /** @return array<string, array{limit: int, used: int}> */
    public function overLimit(): array
    {
        return $this->data['over_limit'] ?? [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }
}
