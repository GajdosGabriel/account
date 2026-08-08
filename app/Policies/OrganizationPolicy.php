<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OrganizationPolicy extends BasePolicy
{
    /**
     * Firma s dokladmi sa natrvalo neodstraňuje.
     *
     * Faktúra musí v evidencii zostať aj po zmazaní firmy – je to
     * desaťročná archivačná povinnosť, nie naša preferencia. V koši
     * firma zostať môže, zmiznúť z databázy nesmie.
     *
     * @param  \App\Models\Organization  $model
     */
    public function forceDelete(User $user, Model $model): bool
    {
        return parent::forceDelete($user, $model) && ! $model->invoices()->exists();
    }
}
