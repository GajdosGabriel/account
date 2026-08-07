<?php

namespace App\Repositories;

use App\Models\Organization;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtrovanie zoznamu organizácií na jednom mieste.
 *
 * Zoznam sa pýta z troch miest (administrácia, API projektov, export) a
 * zakaždým s trochu inou podmienkou. Keď filtre žili priamo v controlleri,
 * „hľadaj podľa IČO“ znamenalo v každom z nich iný `where` – a stačilo
 * pridať štvrté miesto, aby sa výsledky prestali zhodovať.
 *
 * Trieda je nemenná: každý `for*()` vracia novú inštanciu, takže sa
 * pripravený dotaz dá bezpečne podať ďalej bez rizika, že ho niekto
 * cestou prepíše.
 */
class OrganizationQuery
{
    public const PER_PAGE = 25;

    /** Kľúče, ktoré sa čítajú z requestu a vracajú späť do formulára. */
    public const KEYS = ['q', 'status', 'product', 'linked'];

    /** Hodnoty stĺpca `status` – mimo tohto zoznamu sa filter ignoruje. */
    public const STATUSES = ['active', 'suspended', 'archived'];

    /** @var array<string, string|null> */
    protected array $filters;

    /** @param  array<string, mixed>  $filters */
    public function __construct(array $filters = [])
    {
        $this->filters = $this->normalize($filters);
    }

    public static function fromRequest(Request $request): self
    {
        return new self($request->only(self::KEYS));
    }

    /* ---------------------------------------------------------------
     | Zúženie
     |---------------------------------------------------------------*/

    /**
     * Organizácie naviazané na konkrétny projekt.
     *
     * Volá to aj API projektov – token je viazaný na produkt, takže
     * projekt nikdy neuvidí zákazníkov iného projektu.
     */
    public function forProduct(Product|string|null $product): self
    {
        $key = $product instanceof Product ? $product->key : $product;

        return $this->with(['product' => $key, 'linked' => null]);
    }

    /** Fulltext cez názov, IČO a obchodné meno. */
    public function search(?string $term): self
    {
        return $this->with(['q' => $term]);
    }

    /* ---------------------------------------------------------------
     | Výsledok
     |---------------------------------------------------------------*/

    /** @return Builder<Organization> */
    public function builder(): Builder
    {
        return Organization::query()
            ->withCount('products')
            ->when($this->filters['q'], function (Builder $query, string $term) {
                $like = '%'.$term.'%';

                $query->where(fn (Builder $q) => $q
                    ->where('name', 'like', $like)
                    ->orWhere('legal_name', 'like', $like)
                    ->orWhere('ico', 'like', $like));
            })
            ->when($this->filters['status'], fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($this->filters['product'], fn (Builder $q, string $key) => $q->whereHas(
                'products',
                fn (Builder $p) => $p->where('products.key', $key),
            ))
            // Firmy bez projektu sú zvyčajne pozostatok po odviazaní alebo
            // ručnom importe – bez tohto filtra sa v zozname nedajú nájsť.
            ->when($this->filters['linked'] === 'none', fn (Builder $q) => $q->whereDoesntHave('products'))
            ->when($this->filters['linked'] === 'any', fn (Builder $q) => $q->whereHas('products'))
            ->orderBy('name');
    }

    /** @return LengthAwarePaginator<int, Organization> */
    public function paginate(int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return $this->builder()->paginate($perPage)->withQueryString();
    }

    /**
     * Hodnoty filtrov späť do formulára. Prázdne kľúče sa vyhadzujú,
     * aby v adrese nezostávali `?q=&status=` po vymazaní políčka.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter($this->filters, fn ($value) => $value !== null);
    }

    public function isEmpty(): bool
    {
        return $this->toArray() === [];
    }

    /* ---------------------------------------------------------------
     | Pomocné
     |---------------------------------------------------------------*/

    /** @param  array<string, mixed>  $overrides */
    protected function with(array $overrides): self
    {
        return new self(array_merge($this->filters, $overrides));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string|null>
     */
    protected function normalize(array $filters): array
    {
        $clean = [];

        foreach (self::KEYS as $key) {
            $value = $filters[$key] ?? null;
            $value = is_string($value) ? trim($value) : null;
            $clean[$key] = $value === '' ? null : $value;
        }

        // Neznámy stav je preklep v adrese, nie požiadavka na prázdny výpis –
        // radšej filter ignorujeme, než by používateľ hľadal, kam sa podeli dáta.
        if ($clean['status'] !== null && ! in_array($clean['status'], self::STATUSES, true)) {
            $clean['status'] = null;
        }

        if ($clean['linked'] !== null && ! in_array($clean['linked'], ['any', 'none'], true)) {
            $clean['linked'] = null;
        }

        // Filter podľa projektu a „bez projektu“ si protirečia.
        if ($clean['product'] !== null) {
            $clean['linked'] = null;
        }

        return $clean;
    }
}
