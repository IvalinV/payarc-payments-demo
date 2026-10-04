<?php

namespace App\Containers\AppSection\Payments\Policies;

use App\Containers\AppSection\Payments\Models\Agent;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Policies\Policy as ParentPolicy;

final class AgentPolicy extends ParentPolicy
{
    public function before(User $user): bool|null
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('agents.manage');
    }

    public function view(User $user, Agent $agent): bool
    {
        return $user->can('agents.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('agents.manage');
    }

    public function update(User $user, Agent $agent): bool
    {
        return $user->can('agents.manage');
    }

    public function delete(User $user, Agent $agent): bool
    {
        return $user->can('agents.manage');
    }
}
