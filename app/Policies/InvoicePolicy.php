<?php

namespace App\Policies;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\User;

/**
 * Pravidlá práce s dokladmi.
 *
 * Toto nie je len „kto sa smie prihlásiť“. Väčšina pravidiel tu vychádza
 * zo zákona o účtovníctve a z DPH – vystavený doklad je nemenný záznam,
 * nie riadok v tabuľke. Preto:
 *
 *   - meniť a mazať sa dá LEN koncept,
 *   - vystavený doklad sa opravuje dobropisom,
 *   - stornovať sa dá len to, čo nemá evidovanú úhradu,
 *   - odosielať sa dá len vystavený doklad.
 *
 * Frontend si tie isté pravidlá pýta cez InvoiceResource::abilities()
 * a podľa nich skladá položky v dropdown menu. Jeden zdroj pravdy –
 * tlačidlo sa nikdy neukáže pre akciu, ktorú by server odmietol.
 */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** Obsah dokladu sa smie meniť len kým je konceptom. */
    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->isDraft();
    }

    /** Zmazať sa dá len koncept – vystavené číslo nesmie v rade chýbať. */
    public function delete(User $user, Invoice $invoice): bool
    {
        return $invoice->isDraft();
    }

    /** Vystavenie: koncept, ktorý má položky a firma má kompletné údaje. */
    public function issue(User $user, Invoice $invoice): bool
    {
        return $invoice->isDraft()
            && $invoice->items()->exists()
            && $invoice->organization?->missingBillingFields() === [];
    }

    /** Odoslať sa dá vystavený doklad, ktorý nie je stornovaný. */
    public function send(User $user, Invoice $invoice): bool
    {
        return ! $invoice->isDraft()
            && ! $invoice->isCancelled()
            && filled($invoice->recipientEmail());
    }

    /** Upomienka má zmysel len pri dokladoch po splatnosti. */
    public function remind(User $user, Invoice $invoice): bool
    {
        return $invoice->isOverdue()
            && ! $invoice->isCreditNote()
            && filled($invoice->recipientEmail());
    }

    /** Zápis úhrady – kým doklad nie je úplne zaplatený alebo stornovaný. */
    public function pay(User $user, Invoice $invoice): bool
    {
        return ! $invoice->isDraft()
            && ! $invoice->isCancelled()
            && $invoice->outstandingCents() > 0;
    }

    /** Storno len bez evidovanej platby – inak treba dobropis. */
    public function cancel(User $user, Invoice $invoice): bool
    {
        return ! $invoice->isDraft()
            && ! $invoice->isCancelled()
            && $invoice->paid_cents === 0;
    }

    /** Dobropis sa vystavuje k vystavenej riadnej faktúre, a to raz. */
    public function credit(User $user, Invoice $invoice): bool
    {
        return $invoice->type === InvoiceType::Invoice
            && ! $invoice->isDraft()
            && ! $invoice->isCancelled()
            && ! $invoice->children()->where('type', InvoiceType::CreditNote->value)->exists();
    }

    /** Riadna faktúra k zálohovej – len raz a až po úhrade zálohy. */
    public function convert(User $user, Invoice $invoice): bool
    {
        return $invoice->type === InvoiceType::Proforma
            && ! $invoice->isDraft()
            && ! $invoice->isCancelled()
            && ! $invoice->children()->where('type', InvoiceType::Invoice->value)->exists();
    }

    /** PDF sa dá stiahnuť vždy, aj pri koncepte (na kontrolu pred vystavením). */
    public function download(User $user, Invoice $invoice): bool
    {
        return true;
    }

    /** Kópiu si vieme spraviť z čohokoľvek – vzniká nový koncept. */
    public function duplicate(User $user, Invoice $invoice): bool
    {
        return true;
    }

    public function export(User $user): bool
    {
        return true;
    }

    /* ---------------------------------------------------------------
     | Kôš
     |---------------------------------------------------------------*/

    /** Zmazané koncepty sa dajú vrátiť späť – preto sa mažú mäkko. */
    public function restore(User $user, Invoice $invoice): bool
    {
        return $invoice->trashed();
    }

    /**
     * Nenávratné zmazanie.
     *
     * Len koncept, ktorý nikdy nedostal číslo. Vystavený doklad musí
     * v databáze zostať aj po zmazaní – to je desaťročná archivačná
     * povinnosť, nie naša preferencia.
     */
    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return $invoice->trashed() && blank($invoice->number);
    }
}
