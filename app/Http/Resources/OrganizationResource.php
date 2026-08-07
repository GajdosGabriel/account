<?php

namespace App\Http\Resources;

use App\Enums\AddressType;
use App\Models\OrganizationAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Organization
 */
class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'legal_form' => $this->legal_form?->value,
            'legal_form_label' => $this->legal_form?->shortLabel(),
            'status' => $this->status,

            'identifiers' => [
                'ico' => $this->ico,
                'dic' => $this->dic,
                'ic_dph' => $this->ic_dph,
                'vat_mode' => $this->vat_mode?->value,
                'is_vat_payer' => $this->isVatPayer(),
                'oss_registered' => $this->oss_registered,
                'ico_verified_at' => $this->ico_verified_at?->toIso8601String(),
                'vat_verified_at' => $this->vat_verified_at?->toIso8601String(),
            ],

            'registration' => [
                'court' => $this->register_court,
                'section' => $this->register_section,
                'insert' => $this->register_insert,
                'line' => $this->registrationLine(),
                'established_at' => $this->established_at?->toDateString(),
            ],

            // sídlo / miesto podnikania
            'address' => $this->registeredAddress(),

            // adresa na obálku – buď poštová, alebo sídlo
            'mailing_lines' => $this->mailingLines(),

            'addresses' => $this->whenLoaded('addresses', fn () => $this->addresses->map(fn (OrganizationAddress $a) => [
                'id' => $a->id,
                'type' => $a->type->value,
                'label' => $a->label,
                'recipient' => $a->recipient,
                'street' => $a->streetLine(),
                'city' => $a->city,
                'postal_code' => $a->postal_code,
                'region' => $a->region,
                'country' => $a->country,
                'phone' => $a->phone,
                'note' => $a->note,
                'is_default' => $a->is_default,
                'line' => $a->line(),
            ])),

            'contacts' => $this->whenLoaded('contacts', fn () => $this->contacts->map(fn ($c) => [
                'id' => $c->id,
                'type' => $c->type,
                'name' => $c->name,
                'position' => $c->position,
                'email' => $c->email,
                'phone' => $c->phone,
                'is_primary' => $c->is_primary,
            ])),

            'contact' => [
                'email' => $this->email,
                'billing_email' => $this->billing_email,
                'phone' => $this->phone,
                'website' => $this->website,
            ],

            'bank' => [
                'name' => $this->bank_name,
                'iban' => $this->iban,
                'swift' => $this->swift,
            ],

            'billing' => [
                'currency' => $this->currency,
                'payment_terms_days' => $this->payment_terms_days,
                'payment_method' => $this->payment_method,
                'invoice_language' => $this->invoice_language,
                'invoice_delivery' => $this->invoice_delivery,
                'supplier_number' => $this->supplier_number,
                // čo chýba, aby sa dala vystaviť faktúra
                'missing' => $this->missingBillingFields(),
            ],

            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
