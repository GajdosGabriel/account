<?php

namespace App\Http\Middleware;

use App\Models\ServiceClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentifikácia volaní z pripojených projektov.
 *
 *   Authorization: Bearer acc_xxxxxxxx
 *
 * Token je viazaný na produkt. Vďaka tomu API automaticky vie,
 * ktorý projekt sa pýta, a vracia iba to, čo sa ho týka –
 * projekt 1 sa k limitom ani zákazníkom projektu 2 nedostane.
 */
class ServiceTokenAuthentication
{
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => __('messages.token.missing')], 401);
        }

        $client = ServiceClient::findByPlainToken($token);

        if (! $client) {
            return response()->json(['message' => __('messages.token.invalid')], 401);
        }

        if ($ability && ! $client->hasAbility($ability)) {
            return response()->json(['message' => __('messages.token.ability', ['ability' => $ability])], 403);
        }

        if (! $client->product->is_active) {
            return response()->json(['message' => __('messages.token.product_inactive')], 403);
        }

        // Zapisujeme najviac raz za minútu, aby sme nezaťažovali DB.
        if (! $client->last_used_at || $client->last_used_at->lt(now()->subMinute())) {
            $client->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set('service_client', $client);
        $request->attributes->set('service_product', $client->product);

        return $next($request);
    }
}
