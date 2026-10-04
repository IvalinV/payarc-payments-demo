<?php

namespace App\Containers\AppSection\User\Providers;

use App\Ship\Parents\Providers\ServiceProvider as ParentServiceProvider;
use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\User\Observers\UserRoleObserver;
use Illuminate\Validation\Rules\Password;

final class UserServiceProvider extends ParentServiceProvider
{
    public function boot(): void
    {
        User::observe(UserRoleObserver::class);

        Password::defaults(static fn () => Password::min(8)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols());
    }
}
