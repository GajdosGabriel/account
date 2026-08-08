<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OrganizationAddressPolicy extends BasePolicy
{
    public function view(User $user, Model $model): bool
    {
        return false;
    }
}
