<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    /** Produkt, ktorému patrí použitý service token. */
    protected function product(Request $request): Product
    {
        $product = $request->attributes->get('service_product');

        abort_unless($product instanceof Product, 403, __('messages.token.no_product'));

        return $product;
    }

    /**
     * Firma musí byť naviazaná na volajúci projekt.
     * Bez tejto kontroly by projekt vedel čítať cudzích zákazníkov.
     */
    protected function ensureLinked(Organization $organization, Product $product): void
    {
        abort_unless(
            $organization->isLinkedTo($product),
            404,
            __('messages.organization.not_linked'),
        );
    }
}
