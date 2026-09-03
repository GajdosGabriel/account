<?php

namespace App\Policies;

use App\Models\ServiceClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Token sa neotvára na detail, ale má vlastnú stránku úpravy –
 * v menu je preto úprava, zrušenie a zmazanie, nie „zobraziť“.
 *
 * Kôš tu nie je: token, ktorý má zostať v evidencii, sa zruší (revoke),
 * nie zmaže. Zmazanie je preto natvrdo a bez návratu.
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
     * zrušiť len raz.
     *
     * @param  ServiceClient  $model
     */
    public function revoke(User $user, Model $model): bool
    {
        return ! $model->isRevoked();
    }

    /**
     * Zrušenie sa dá vziať späť.
     *
     * V databáze zostal hash pôvodného tokenu, takže projektu stačí
     * povolenie – nemusí si nikam vymieňať hodnotu. Preto to nie je
     * vydanie nového tokenu, ale návrat toho istého.
     *
     * @param  ServiceClient  $model
     */
    public function unrevoke(User $user, Model $model): bool
    {
        return $model->isRevoked();
    }
}
