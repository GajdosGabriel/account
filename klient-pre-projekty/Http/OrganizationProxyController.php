<?php

namespace App\Http\Controllers;

use App\Services\AccountClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Formulár firemných údajov vo VLASTNOM projekte.
 *
 * Používateľ vidí stránku projektu a netuší, že sa dáta ukladajú inde.
 * Validáciu robí Account, chyby sa zobrazia pri poliach ako obvykle.
 */
class OrganizationProxyController extends Controller
{
    public function __construct(private readonly AccountClient $account) {}

    public function edit(Request $request)
    {
        $organization = $this->account->organization($request->user()->organization_id);

        abort_if($organization === null, 503, 'Údaje o firme sa nepodarilo načítať.');

        return view('organization.edit', compact('organization'));
    }

    public function update(Request $request): RedirectResponse
    {
        // Nevaliduj tu – pravidlá pre IČO a IČ DPH sú v Accounte,
        // aby sa všetky projekty správali rovnako.
        $this->account->updateOrganization(
            $request->user()->organization_id,
            $request->only([
                'name', 'legal_name', 'legal_form',
                'ico', 'dic', 'ic_dph', 'vat_mode',
                'register_court', 'register_section', 'register_insert',
                'street', 'street_no', 'city', 'postal_code', 'region', 'country',
                'email', 'billing_email', 'phone', 'website',
                'bank_name', 'iban', 'swift',
            ]),
        );

        return back()->with('success', 'Údaje firmy boli uložené.');
    }

    /** Predvyplnenie formulára z registra RPO (AJAX). */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['ico' => ['required', 'string', 'max:12']]);

        return response()->json(
            $this->account->lookupIco($request->string('ico')->toString())
        );
    }
}
