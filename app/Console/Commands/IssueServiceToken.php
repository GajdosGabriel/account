<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ServiceClient;
use Illuminate\Console\Command;

class IssueServiceToken extends Command
{
    protected $signature = 'accounts:issue-token {product : kľúč produktu} {name : popis tokenu}';

    protected $description = 'Vygeneruje service token, ktorým sa projekt autentifikuje voči /api/v1.';

    public function handle(): int
    {
        $product = Product::where('key', $this->argument('product'))->first();

        if (! $product) {
            $this->error('Produkt sa nenašiel: '.$this->argument('product'));

            return self::FAILURE;
        }

        [$client, $plain] = ServiceClient::issue($product, $this->argument('name'));

        $this->info('Token vytvorený. Zobrazí sa iba teraz, ulož si ho:');
        $this->newLine();
        $this->line($plain);
        $this->newLine();
        $this->comment("Prefix: {$client->token_prefix} · Produkt: {$product->name}");

        return self::SUCCESS;
    }
}
