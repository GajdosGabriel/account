<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Spoločné pravidlá pre záznamy, ktoré sa mažú mäkko.
 *
 * Základ je vždy rovnaký a plynie z toho, ako sa kôš správa:
 *
 *   - živý záznam sa dá zobraziť, upraviť a vymazať (do koša),
 *   - záznam v koši sa dá upraviť, obnoviť alebo odstrániť natrvalo.
 *
 * Úprava v koši je zámerná: opraviť záznam ešte pred návratom je
 * bežnejšie než ho najprv obnoviť a hneď nato opravovať. Do koša
 * sa druhý raz zahodiť nedá – na to je `delete`.
 *
 * Potomkovia dopĺňajú len to, čo je pre danú entitu naozaj iné –
 * napríklad projekt sa nedá zmazať, kým ho používajú firmy.
 *
 * Parametre sú typované na Model zámerne: PHP nepovoľuje zúžiť typ
 * v potomkovi. Konkrétny typ dopĺňa @var v tele metódy.
 */
abstract class BasePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return ! $this->trashed($model);
    }

    public function update(User $user, Model $model): bool
    {
        return true;
    }

    /** Mazanie je vždy mäkké – preto sa dá aj vrátiť späť. */
    public function delete(User $user, Model $model): bool
    {
        return ! $this->trashed($model);
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->trashed($model);
    }

    /** Natrvalo sa odstraňuje len to, čo už v koši je. */
    public function forceDelete(User $user, Model $model): bool
    {
        return $this->trashed($model);
    }

    protected function trashed(Model $model): bool
    {
        return method_exists($model, 'trashed') && $model->trashed();
    }
}
