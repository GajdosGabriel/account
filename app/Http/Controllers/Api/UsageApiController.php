<?php

namespace App\Http\Controllers\Api;

use App\Models\Organization;
use App\Services\Entitlements\EntitlementService;
use App\Services\Usage\UsageRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Projekt sem hlási, koľko toho firma reálne používa.
 *
 * Limit vynucuje projekt (iba on vidí svoje dáta), ale bez tohto
 * hlásenia by si ako prevádzkovateľ nevidel, kto sa blíži k stropu.
 */
class UsageApiController extends ApiController
{
    public function __construct(
        private readonly UsageRecorder $recorder,
        private readonly EntitlementService $entitlements,
    ) {}

    public function store(Request $request, Organization $organization): JsonResponse
    {
        $product = $this->product($request);
        $this->ensureLinked($organization, $product);

        $data = $request->validate([
            'metrics' => ['required', 'array'],
            'metrics.*' => ['required', 'integer', 'min:0'],
        ]);

        $saved = $this->recorder->record($organization, $product, $data['metrics']);

        // Vrátime rovno aktuálne entitlements, nech projekt nemusí volať druhýkrát.
        return response()->json([
            'recorded' => $saved,
            'data' => $this->entitlements->for($organization, $product, fresh: true),
        ]);
    }
}
