<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\OrganizationApiRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Services\Registry\IcoLookupService;
use App\Services\Registry\ViesValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class OrganizationApiController extends ApiController
{
    /** Zoznam firiem naviazaných na volajúci projekt. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $product = $this->product($request);

        return OrganizationResource::collection(
            Organization::forProduct($product)
                ->when($request->filled('q'), function ($query) use ($request) {
                    $term = '%'.$request->string('q').'%';
                    $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('ico', 'like', $term));
                })
                ->orderBy('name')
                ->paginate(50)
        );
    }

    public function show(Request $request, Organization $organization): OrganizationResource
    {
        $this->ensureLinked($organization, $this->product($request));

        return new OrganizationResource($organization->load(['addresses', 'contacts']));
    }

    /**
     * Založenie firmy z projektu.
     *
     * Kľúčové je, že podľa IČO firmu najprv HĽADÁME. Ak už existuje
     * z iného projektu, iba sa na ňu naviažeme – inak by si po čase
     * mal tri záznamy tej istej firmy a celá centralizácia by stratila zmysel.
     */
    public function store(OrganizationApiRequest $request): JsonResponse
    {
        $product = $this->product($request);
        $data = $request->validated();
        $attributes = $request->organizationData();

        $existing = filled($data['ico'] ?? null)
            ? Organization::withTrashed()->where('ico', $data['ico'])->first()
            : null;

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->linkTo($product, $data['external_ref'] ?? null);

            AuditLog::record('organization.linked', $existing, ['product' => $product->key], $existing->id, $product->id);

            return response()->json([
                'data' => new OrganizationResource($existing),
                'created' => false,
            ], 200);
        }

        $organization = DB::transaction(function () use ($attributes, $data, $product) {
            $organization = Organization::create($attributes);
            $organization->linkTo($product, $data['external_ref'] ?? null);

            return $organization;
        });

        AuditLog::record('organization.created', $organization, ['product' => $product->key], $organization->id, $product->id);

        return response()->json([
            'data' => new OrganizationResource($organization),
            'created' => true,
        ], 201);
    }

    /**
     * Úprava z formulára projektu.
     *
     * Validačné chyby vraciame v 422 v tvare, ktorý Laravel na strane
     * projektu vie priamo hodiť do formulára – používateľ netuší,
     * že prišli odinakiaľ.
     */
    public function update(OrganizationApiRequest $request, Organization $organization): OrganizationResource
    {
        $product = $this->product($request);
        $this->ensureLinked($organization, $product);

        $organization->fill($request->organizationData());

        if ($organization->isDirty('ic_dph') && filled($organization->ic_dph)) {
            $result = app(ViesValidator::class)->validate($organization->ic_dph);
            $organization->vat_verified_at = ($result['valid'] ?? false) ? now() : null;
        }

        if ($organization->isDirty('ico') && filled($organization->ico)) {
            $organization->ico_verified_at = null;
        }

        $changed = array_keys($organization->getDirty());
        $organization->save();

        AuditLog::record('organization.updated', $organization, ['changed' => $changed, 'product' => $product->key], $organization->id, $product->id);

        return new OrganizationResource($organization);
    }

    /** Vyhľadanie firmy v registri RPO (SK) alebo ARES (CZ) – projekt tak vie predvyplniť formulár. */
    public function lookup(Request $request, IcoLookupService $lookup): JsonResponse
    {
        $this->product($request);

        $request->validate([
            'ico' => ['required', 'string', 'max:12'],
            'country' => ['nullable', 'string', 'max:2'],
        ]);

        return response()->json(['data' => $lookup->lookup(
            $request->string('ico')->toString(),
            $request->string('country', 'sk')->toString(),
        )]);
    }
}
