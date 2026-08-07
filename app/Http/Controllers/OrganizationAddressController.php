<?php

namespace App\Http\Controllers;

use App\Enums\AddressType;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationAddress;
use App\Models\OrganizationContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ďalšie adresy a kontaktné osoby firmy.
 * Sídlo sa upravuje priamo na organizácii.
 */
class OrganizationAddressController extends Controller
{
    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $data = $this->validateAddress($request);

        $organization->addresses()->create($data);

        AuditLog::record('address.added', $organization, ['type' => $data['type']], $organization->id);

        return back()->with('success', 'Adresa bola pridaná.');
    }

    public function update(Request $request, Organization $organization, OrganizationAddress $address): RedirectResponse
    {
        abort_unless($address->organization_id === $organization->id, 404);

        $address->update($this->validateAddress($request));

        return back()->with('success', 'Adresa bola upravená.');
    }

    public function destroy(Organization $organization, OrganizationAddress $address): RedirectResponse
    {
        abort_unless($address->organization_id === $organization->id, 404);

        $address->delete();

        return back()->with('success', 'Adresa bola odstránená.');
    }

    public function storeContact(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(OrganizationContact::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:500'],
            'is_primary' => ['boolean'],
        ]);

        $organization->contacts()->create($data);

        return back()->with('success', 'Kontaktná osoba bola pridaná.');
    }

    public function destroyContact(Organization $organization, OrganizationContact $contact): RedirectResponse
    {
        abort_unless($contact->organization_id === $organization->id, 404);

        $contact->delete();

        return back()->with('success', 'Kontakt bol odstránený.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateAddress(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(AddressType::class)],
            'label' => ['nullable', 'string', 'max:120'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'street_no' => ['nullable', 'string', 'max:30'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:12'],
            'region' => ['nullable', 'string', 'max:80'],
            'country' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:500'],
            'is_default' => ['boolean'],
        ]);

        $data['country'] = strtoupper($data['country']);

        return $data;
    }
}
