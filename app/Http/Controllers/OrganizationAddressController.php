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
        $this->belongsTo($address, $organization);
        $this->authorize('update', $address);

        $address->update($this->validateAddress($request));

        return back()->with('success', 'Adresa bola upravená.');
    }

    public function destroy(Organization $organization, OrganizationAddress $address): RedirectResponse
    {
        $this->belongsTo($address, $organization);
        $this->authorize('delete', $address);

        $address->delete();

        return back()->with('success', __('actions.flash.deleted'));
    }

    public function restore(Organization $organization, OrganizationAddress $address): RedirectResponse
    {
        $this->belongsTo($address, $organization);
        $this->authorize('restore', $address);

        $address->restore();

        return back()->with('success', __('actions.flash.restored'));
    }

    public function forceDelete(Organization $organization, OrganizationAddress $address): RedirectResponse
    {
        $this->belongsTo($address, $organization);
        $this->authorize('forceDelete', $address);

        $address->forceDelete();

        return back()->with('success', __('actions.flash.force_deleted'));
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

    public function updateContact(Request $request, Organization $organization, OrganizationContact $contact): RedirectResponse
    {
        $this->belongsTo($contact, $organization);
        $this->authorize('update', $contact);

        $contact->update($request->validate([
            'type' => ['required', Rule::in(OrganizationContact::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:500'],
            'is_primary' => ['boolean'],
        ]));

        return back()->with('success', 'Kontakt bol upravený.');
    }

    public function destroyContact(Organization $organization, OrganizationContact $contact): RedirectResponse
    {
        $this->belongsTo($contact, $organization);
        $this->authorize('delete', $contact);

        $contact->delete();

        return back()->with('success', __('actions.flash.deleted'));
    }

    public function restoreContact(Organization $organization, OrganizationContact $contact): RedirectResponse
    {
        $this->belongsTo($contact, $organization);
        $this->authorize('restore', $contact);

        $contact->restore();

        return back()->with('success', __('actions.flash.restored'));
    }

    public function forceDeleteContact(Organization $organization, OrganizationContact $contact): RedirectResponse
    {
        $this->belongsTo($contact, $organization);
        $this->authorize('forceDelete', $contact);

        $contact->forceDelete();

        return back()->with('success', __('actions.flash.force_deleted'));
    }

    /**
     * Adresa aj kontakt sa adresujú vlastným id – bez tejto kontroly
     * by sa cez cudzí odkaz dali meniť záznamy inej firmy.
     */
    protected function belongsTo(OrganizationAddress|OrganizationContact $record, Organization $organization): void
    {
        abort_unless($record->organization_id === $organization->id, 404);
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
