<?php

namespace App\Http\Middleware;

use App\Models\Conversation;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'appName' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                // Jednorazová hodnota (token, podpisový kľúč) nejde do textu
                // hlásenia – toast zmizne a skopírovať sa z neho nedá.
                'secret' => fn () => $request->session()->get('secret'),
            ],
            // Popisky akcií drží lang, nie šablóna – rovnaký text tak
            // vidí server aj Vue a preklad je na jednom mieste.
            'locale' => app()->getLocale(),
            'locales' => Locales::options(),
            // Desiatky kB textov idú len pri prvom načítaní. Klient si ich
            // pamätá pod kľúčom s jazykom a verziou lang súborov – po prepnutí
            // jazyka alebo nasadení nových textov ich server pošle znova.
            'translations' => Inertia::once(fn () => [
                'actions' => Lang::get('actions'),
                'tokens' => Lang::get('tokens'),
                'common' => Lang::get('common'),
                'enums' => Lang::get('enums'),
                'dashboard' => Lang::get('dashboard'),
                'organizations' => Lang::get('organizations'),
                'invoices' => Lang::get('invoices'),
                'messaging' => Lang::get('messaging'),
                'mailing' => Lang::get('mailing'),
            ])->as('translations:'.app()->getLocale().':'.$this->translationsVersion()),
            // Odznak pri položke Inbox v navigácii
            'inbox_unread' => fn () => $user ? Conversation::notSpam()->where('unread_count', '>', 0)->count() : 0,
        ]);
    }

    /** Mení sa s každou úpravou lang súborov aktuálneho jazyka (aj záložného). */
    private function translationsVersion(): string
    {
        $files = [
            ...glob(lang_path(app()->getLocale().'/*.php')) ?: [],
            ...glob(lang_path(config('app.fallback_locale').'/*.php')) ?: [],
        ];

        return substr(md5(implode('|', array_map(fn ($file) => $file.filemtime($file), $files))), 0, 8);
    }
}
