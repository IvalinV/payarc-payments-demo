<?php

namespace App\Containers\AppSection\Payments\Policies;

use App\Containers\AppSection\Payments\Models\Account;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Policies\Policy as ParentPolicy;

final class AccountPolicy extends ParentPolicy
{
    public function before(User $user): bool|null
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('merchants.manage');
    }

    public function view(User $user, Account $account): bool
    {
        return $user->can('merchants.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('merchants.manage');
    }

    public function update(User $user, Account $account): bool
    {
        return $user->can('merchants.manage');
    }

    public function delete(User $user, Account $account): bool
    {
        return $user->can('merchants.manage');
    }
}
