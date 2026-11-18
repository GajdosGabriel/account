<?php

namespace App\Http\Controllers\Concerns;

use App\Models\MailingContact;
use App\Models\MailingTag;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Údaje, ktoré potrebujú viaceré obrazovky emailingu: zoznam projektov,
 * ich štítky a stav odosielania.
 *
 * Kontrolér, ktorý volá settings(), musí mať vlastnosť $sender (MailingSender).
 */
trait SharesMailingData
{
    /**
     * Štítky projektu: zaregistrované s popisom a k nim tie, ktoré kontakty
     * majú, hoci ich projekt (zatiaľ) nezaregistroval.
     *
     * @return list<array{key: string, label: string, description: ?string}>
     */
    private function productTags(Product $product): array
    {
        $tags = $product->mailingTags->mapWithKeys(fn (MailingTag $t) => [$t->key => [
            'key' => $t->key, 'label' => $t->label, 'description' => $t->description,
        ]])->all();

        // Ručné štítky s názvom zo správy štítkov.
        foreach ($product->mailingLabels as $label) {
            $tags[$label->key] ??= ['key' => $label->key, 'label' => $label->name, 'description' => null, 'manual' => true, 'color' => $label->color];
        }

        foreach ($this->contactTags($product) as $key) {
            $tags[$key] ??= ['key' => $key, 'label' => $key, 'description' => null];
        }

        return array_values($tags);
    }

    /** @return list<string> */
    private function contactTags(Product $product): array
    {
        $decode = fn ($tags) => is_array($tags) ? $tags : (json_decode((string) $tags, true) ?: []);
        $query = fn (string $column) => MailingContact::query()->where('product_id', $product->id)
            ->whereNotNull($column)->distinct()->pluck($column)->flatMap($decode);

        return $query('tags')->merge($query('manual_tags'))->unique()->sort()->values()->all();
    }

    /** @return Collection<int, Product> */
    private function products()
    {
        return Product::query()->orderBy('name')->get(['key', 'name']);
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        return [
            'enabled' => $this->sender->enabled(),
            'dry_run' => $this->sender->dryRun(),
            'hourly_limit' => (int) config('accounts.mailing.hourly_limit'),
            'timezone' => config('app.timezone'),
        ];
    }
}
