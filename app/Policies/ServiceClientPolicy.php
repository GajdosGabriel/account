<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Token sa neotvára na detail, ale má vlastnú stránku úpravy –
 * v menu je preto úprava, zrušenie a kôš, nie „zobraziť“.
 */
class ServiceClientPolicy extends BasePolicy
{
    public function view(User $user, Model $model): bool
    {
        return false;
    }

    /**
     * Zrušenie tokenu nie je mazanie: záznam zostáva v evidencii
     * kvôli auditu, len ním už neprejde autentifikácia. Preto sa dá
     * zrušiť raz a nikdy nie zo záznamu v koši.
     *
     * @param  \App\Models\ServiceClient  $model
     */
    public function revoke(User $user, Model $model): bool
    {
        return ! $this->trashed($model) && ! $model->isRevoked();
    }

    /**
     * Zrušenie sa dá vziať späť.
     *
     * V databáze zostal hash pôvodného tokenu, takže projektu stačí
     * povolenie – nemusí si nikam vymieňať hodnotu. Preto to nie je
     * vydanie nového tokenu, ale návrat toho istého.
     *
     * @param  \App\Models\ServiceClient  $model
     */
    public function unrevoke(User $user, Model $model): bool
    {
        return ! $this->trashed($model) && $model->isRevoked();
    }
}
