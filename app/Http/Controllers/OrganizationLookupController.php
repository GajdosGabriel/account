<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\Registry\IcoLookupService;
use App\Services\Registry\ViesValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AJAX endpointy pre formulár organizácie – predvyplnenie z RPO
 * a overenie IČ DPH vo VIES bez opustenia stránky.
 */
class OrganizationLookupController extends Controller
{
    /**
     * Najprv hľadáme v našej databáze – firmu, ktorú už máme, netreba ťahať
     * z registra a hlavne netreba založiť druhýkrát. Register je až záloha.
     */
    public function ico(Request $request, IcoLookupService $lookup): JsonResponse
    {
        $request->validate([
            'ico' => ['required', 'string', 'max:12'],
            'country' => ['nullable', 'string', 'max:2'],
            // pri úprave nesmieme ako duplikát hlásiť sami seba
            'exclude' => ['nullable', 'string', 'max:64'],
        ]);

        $ico = $lookup->normalize($request->string('ico')->toString());

        if ($existing = $this->findInDatabase($ico, $request->string('exclude')->toString())) {
            return response()->json($existing);
        }

        return response()->json($lookup->lookup(
            $ico,
            $request->string('country', 'sk')->toString(),
        ));
    }

    /**
     * Existujúca firma z našej DB v tvare, ktorý formulár už vie spracovať.
     * Archivované a zmazané hľadáme tiež – inak by sa založil duplikát.
     *
     * @return array<string, mixed>|null
     */
    protected function findInDatabase(string $ico, string $exclude = ''): ?array
    {
        $organization = Organization::withTrashed()
            ->where('ico', $ico)
            ->when($exclude !== '', fn ($q) => $q->where('uuid', '!=', $exclude))
            ->first();

        if (! $organization) {
            return null;
        }

        return [
            'found' => true,
            'source' => 'db',
            'organization' => [
                'uuid' => $organization->uuid,
                'name' => $organization->name,
                'status' => $organization->trashed() ? 'deleted' : $organization->status,
                'url' => route('organizations.show', $organization),
            ],

            'name' => $organization->name,
            'legal_name' => $organization->legal_name,
            'legal_form' => $organization->legal_form?->value,
            'ico' => $organization->ico,
            'dic' => $organization->dic,
            'ic_dph' => $organization->ic_dph,

            'street' => $organization->street,
            'street_no' => $organization->street_no,
            'city' => $organization->city,
            'postal_code' => $organization->postal_code,
            'region' => $organization->region,
            'country' => $organization->country,

            'register_court' => $organization->register_court,
            'register_section' => $organization->register_section,
            'register_insert' => $organization->register_insert,
            'established_at' => $organization->established_at?->toDateString(),
        ];
    }

    public function vat(Request $request, ViesValidator $vies): JsonResponse
    {
        $request->validate(['ic_dph' => ['required', 'string', 'max:20']]);

        return response()->json($vies->validate($request->string('ic_dph')->toString()));
    }

    /** Znovu overí uložené údaje organizácie oproti registrom. */
    public function reverify(Request $request, Organization $organization, IcoLookupService $lookup, ViesValidator $vies): JsonResponse
    {
        $result = ['ico' => null, 'vat' => null];

        if (filled($organization->ico)) {
            $result['ico'] = $lookup->lookup($organization->ico, $organization->country ?? 'sk');
            $organization->ico_verified_at = ($result['ico']['found'] ?? false) ? now() : null;
            $organization->registry_snapshot = $result['ico']['raw'] ?? null;
        }

        if (filled($organization->ic_dph)) {
            $result['vat'] = $vies->validate($organization->ic_dph);
            $organization->vat_verified_at = ($result['vat']['valid'] ?? false) ? now() : null;
        }

        $organization->save();

        return response()->json($result);
    }
}
