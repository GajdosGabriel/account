<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProductPolicy extends BasePolicy
{
    /**
     * Projekt používaný firmami sa nemaže – so sebou by vzal plány,
     * katalóg, tokeny aj webhooky, na ktorých tie firmy bežia.
     *
     * @param  \App\Models\Product  $model
     */
    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && ! $model->organizations()->exists();
    }
}
