<?php

namespace App\Observers;

use App\Enums\LegalForm;
use App\Enums\VatMode;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Services\Entitlements\EntitlementService;
use App\Services\Webhooks\WebhookDispatcher;

class OrganizationObserver
{
    public function __construct(
        private readonly WebhookDispatcher $dispatcher,
        private readonly EntitlementService $entitlements,
    ) {}

    /**
     * Súkromná osoba nesmie mať firemné údaje.
     *
     * Je to tu, a nie vo formulári, lebo prepnutie typu môže prísť z admina,
     * z API projektu aj z importu. Keby to riešil každý z nich sám, stačilo
     * by na jednom mieste zabudnúť a občanovi by na faktúre zostalo IČO
     * firmy, ktorou kedysi bol.
     */
    public function saving(Organization $organization): void
    {
        if (! $organization->isPerson()) {
            return;
        }

        $organization->forceFill([
            'ico' => null,
            'dic' => null,
            'ic_dph' => null,
            'ico_verified_at' => null,
            'vat_verified_at' => null,
            'register_court' => null,
            'register_section' => null,
            'register_insert' => null,
            'oss_registered' => false,
            // Občan nie je platiteľ DPH; inak by sa mu na doklad vytlačila.
            'vat_mode' => VatMode::NonPayer,
            'legal_form' => LegalForm::Fyzicka,
        ]);
    }

    /**
     * Nová firma v Accounte.
     *
     * Projekt, ktorý ju práve založil, o nej vie z odpovede API – táto
     * udalosť je pre tie ostatné. Bez nej sa druhý projekt dozvie o firme,
     * ktorá k nemu patrí tiež, až keď ju niekto prvý raz upraví.
     *
     * Entitlements sa tu nezahadzujú, na rozdiel od `updated` – nová firma
     * ešte nemá čo mať v cache.
     */
    public function created(Organization $organization): void
    {
        $this->dispatcher->dispatch('organization.created', [
            'organization' => $this->payload($organization),
        ]);
    }

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

        return (new OrganizationResource($organization))
            ->toArray(request());
    }
}
