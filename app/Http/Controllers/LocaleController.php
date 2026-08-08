<?php

namespace App\Http\Controllers;

use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Prepnutie jazyka rozhrania.
 *
 * Voľba sa drží v session, nie na používateľovi – prihlásiť sa dá
 * z cudzieho počítača a jazyk je vlastnosť prehliadača, nie účtu.
 * Preto je prepínač dostupný aj pred prihlásením.
 */
class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in(Locales::supported())],
        ]);

        $request->session()->put('locale', $data['locale']);

        return back();
    }
}
