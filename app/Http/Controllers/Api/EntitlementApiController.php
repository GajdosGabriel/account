<?php

namespace App\Http\Controllers\Api;

use App\Models\Organization;
use App\Services\Entitlements\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntitlementApiController extends ApiController
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    /**
     * Čo smie firma v tomto projekte.
     *
     * Toto je najčastejšie volaný endpoint – projekt ho ťahá pri každom
     * prihlásení a potom z cache. Preto je odpoveď plochá a bez zbytočností.
     */
    public function show(Request $request, Organization $organization): JsonResponse
    {
        $product = $this->product($request);

        if (! $organization->isLinkedTo($product)) {
            return response()->json([
                'data' => $this->entitlements->unlinked($product, $organization->uuid),
            ]);
        }

        return response()->json([
            'data' => $this->entitlements->for($organization, $product, fresh: $request->boolean('fresh')),
        ]);
    }
}
