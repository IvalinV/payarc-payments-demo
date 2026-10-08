<?php

namespace App\Containers\AppSection\Payments\Policies;

use App\Containers\AppSection\Payments\Models\Charge;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Policies\Policy as ParentPolicy;

final class ChargePolicy extends ParentPolicy
{
    public function before(User $user): bool|null
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('charges.view');
    }

    public function view(User $user, Charge $charge): bool
    {
        return $user->can('charges.view');
    }

    public function create(User $user): bool
    {
        return $user->can('charges.create');
    }

    public function update(User $user, Charge $charge): bool
    {
        return $user->can('charges.create');
    }

    public function delete(User $user, Charge $charge): bool
    {
        return false;
    }
}
