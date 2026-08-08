<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PlanPolicy extends BasePolicy
{
    public function view(User $user, Model $model): bool
    {
        return false;
    }

    /**
     * Plán, na ktorom niekto visí, sa nemaže – zákazník by zostal
     * s predplatným bez cenníka.
     *
     * @param  \App\Models\Plan  $model
     */
    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && ! $model->subscriptions()->exists();
    }
}
