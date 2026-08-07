<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductFeature>
 */
class ProductFeatureFactory extends Factory
{
    protected $model = ProductFeature::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'key' => 'max_records',
            'name' => 'Počet záznamov',
            'type' => ProductFeature::TYPE_LIMIT,
            'unit' => 'záznamov',
            'metric' => 'records',
            'default_value' => ['value' => 0],
            'sort_order' => 0,
        ];
    }

    public function flag(string $key = 'export', string $name = 'Export'): static
    {
        return $this->state(fn () => [
            'key' => $key,
            'name' => $name,
            'type' => ProductFeature::TYPE_FLAG,
            'unit' => null,
            'metric' => null,
            'default_value' => ['value' => false],
        ]);
    }
}
