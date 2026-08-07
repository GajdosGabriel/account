<?php

namespace App\Observers;

use App\Models\Organization;
use App\Services\Entitlements\EntitlementService;
use App\Services\Webhooks\WebhookDispatcher;

class OrganizationObserver
{
    public function __construct(
        private readonly WebhookDispatcher $dispatcher,
        private readonly EntitlementService $entitlements,
    ) {}

    public function updated(Organization $organization): void
    {
        $this->entitlements->flush($organization);

        $this->dispatcher->dispatch('organization.updated', [
            'organization' => $this->payload($organization),
            'changed' => array_keys($organization->getChanges()),
        ]);
    }

    public function deleted(Organization $organization): void
    {
        $this->entitlements->flush($organization);

        $this->dispatcher->dispatch('organization.deleted', [
            'organization_id' => $organization->uuid,
        ]);
    }

    /** @return array<string, mixed> */
    protected function payload(Organization $organization): array
    {
        // whenLoaded() by bez načítaných vzťahov nechal v poli MissingValue,
        // ktorý sa do webhooku nesmie dostať.
        $organization->loadMissing(['addresses', 'contacts']);

        return (new \App\Http\Resources\OrganizationResource($organization))
            ->toArray(request());
    }
}
