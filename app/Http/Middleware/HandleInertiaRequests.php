<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
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
            ],
            // Popisky akcií drží lang, nie šablóna – rovnaký text tak
            // vidí server aj Vue a preklad je na jednom mieste.
            'locale' => app()->getLocale(),
            'locales' => Locales::options(),
            'translations' => fn () => [
                'actions' => Lang::get('actions'),
                'tokens' => Lang::get('tokens'),
            ],
        ]);
    }
}
