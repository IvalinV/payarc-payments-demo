<?php

namespace App\Containers\AppSection\User\Policies;

use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Policies\Policy as ParentPolicy;

final class UserPolicy extends ParentPolicy
{
    public function before(User $user): bool|null
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function delete(?User $user = null, ?User $target = null): bool
    {
        return $user !== null && $target !== null && $user->can('users.manage') && ! $user->is($target);
    }

    public function view(User $user, ?User $target): bool
    {
        return $target !== null && ($user->is($target) || $user->can('users.manage'));
    }

    public function update(User $user, ?User $target): bool
    {
        return $target !== null && ($user->is($target) || $user->can('users.manage'));
    }

    public function index(): bool
    {
        return false;
    }
}
