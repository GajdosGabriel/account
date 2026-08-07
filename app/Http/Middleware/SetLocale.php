<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nastaví jazyk odpovede.
 *
 * Dôležité pri API: validačné chyby z formulára projektu vidí koncový
 * zákazník, nie my. Jazyk sa preto musí riadiť ním, nie predvoleným
 * jazykom Accountu.
 *
 * Poradie: ?lang= → hlavička Accept-Language → predvolený jazyk.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('accounts.locales', ['sk']);

        $locale = $this->fromQuery($request, $supported)
            ?? $this->fromHeader($request, $supported)
            ?? config('app.locale');

        App::setLocale($locale);

        return $next($request);
    }

    /** @param array<int, string> $supported */
    protected function fromQuery(Request $request, array $supported): ?string
    {
        $lang = strtolower((string) $request->query('lang', ''));

        return in_array($lang, $supported, true) ? $lang : null;
    }

    /**
     * Accept-Language: de-DE,de;q=0.9,en;q=0.8
     *
     * @param  array<int, string>  $supported
     */
    protected function fromHeader(Request $request, array $supported): ?string
    {
        $header = $request->header('Accept-Language');

        if (! $header) {
            return null;
        }

        foreach (explode(',', $header) as $part) {
            $code = strtolower(trim(explode(';', $part)[0]));
            $code = explode('-', $code)[0];

            if (in_array($code, $supported, true)) {
                return $code;
            }
        }

        return null;
    }
}
