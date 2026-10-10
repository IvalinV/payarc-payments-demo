<?php

namespace App\Containers\AppSection\User\Observers;

use App\Containers\AppSection\User\Models\User;

final class UserRoleObserver
{
    public function saved(User $user): void
    {
        $this->syncFlags($user);
    }

    public function syncFlags(User $user): void
    {
        $roles = $user->roles()
            ->where('guard_name', 'api')
            ->pluck('name');

        $flags = [
            'is_admin' => $roles->contains('admin'),
            'is_agent' => $roles->contains('agent'),
            'is_merchant' => $roles->contains('merchant'),
        ];

        if ($user->only(array_keys($flags)) === $flags) {
            return;
        }

        $user->forceFill($flags)->saveQuietly();
    }
}
