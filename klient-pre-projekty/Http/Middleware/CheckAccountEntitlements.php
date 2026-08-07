<?php

namespace App\Http\Middleware;

use App\Services\AccountClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rozhodne, či smie prihlásený používateľ pokračovať.
 *
 * Prihlásenie rieši projekt sám – toto je iba kontrola predplatného
 * a limitov. Keď Account nebeží, použije sa posledná známa hodnota
 * a aplikácia beží ďalej.
 *
 * Registrácia v bootstrap/app.php:
 *
 *   $middleware->alias(['entitlements' => CheckAccountEntitlements::class]);
 */
class CheckAccountEntitlements
{
    public function __construct(private readonly AccountClient $account) {}

    public function handle(Request $request, Closure $next): Response
    {
        $organizationId = $request->user()?->organization_id;

        // Používateľ bez firmy – nech si ju najprv založí.
        if (! $organizationId) {
            return $next($request);
        }

        $entitlements = $this->account->entitlements($organizationId);

        // Úplne zamknuté – ukáž platobnú stránku, nie 403.
        if ($entitlements->isLocked()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Predplatné nie je aktívne.', 'status' => $entitlements->status()], 402)
                : redirect()->route('billing.locked');
        }

        // Read-only – čítanie povolené, zápis nie.
        if ($entitlements->isReadOnly() && ! $request->isMethodSafe()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Predplatné je pozastavené.'], 402)
                : redirect()->route('billing.locked')
                    ->with('error', 'Predplatné je pozastavené. Po úhrade sa zápis obnoví.');
        }

        // Sprístupníme entitlements zvyšku aplikácie aj šablónam.
        $request->attributes->set('entitlements', $entitlements);
        view()->share('entitlements', $entitlements);

        return $next($request);
    }
}
