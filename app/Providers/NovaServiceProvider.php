<?php

namespace App\Providers;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\Payments\Models\Account;
use App\Containers\AppSection\Payments\Models\Agent;
use App\Containers\AppSection\Payments\Models\Charge;
use App\Containers\AppSection\Payments\Policies\AccountPolicy;
use App\Containers\AppSection\Payments\Policies\AgentPolicy;
use App\Containers\AppSection\Payments\Policies\ChargePolicy;
use App\Containers\AppSection\User\Policies\UserPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;
use Laravel\Fortify\Features;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(Agent::class, AgentPolicy::class);
        Gate::policy(Charge::class, ChargePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }

    /**
     * Register the configurations for Laravel Fortify.
     */
    protected function fortify(): void
    {
        Nova::fortify()
            ->features([
                Features::updatePasswords(),
                // Features::emailVerification(),
                // Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
                // Features::passkeys(),
            ])
            ->authenticateUsing(function (Request $request): ?User {
                $user = User::query()->where('email', $request->input('email'))->first();

                if ($user === null || ! Hash::check((string) $request->input('password'), (string) $user->getAuthPassword())) {
                    return null;
                }

                return $user->hasRole('admin', 'web') && $user->can('admin.access')
                    ? $user
                    : null;
            })
            ->register();
    }

    /**
     * Register the Nova routes.
     */
    protected function routes(): void
    {
        Nova::routes()
            ->withAuthenticationRoutes()
            ->withPasswordResetRoutes()
            ->withoutEmailVerificationRoutes()
            ->register();
    }

    /**
     * Register the Nova gate.
     *
     * This gate determines who can access Nova in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewNova', function (User $user) {
            return $user->hasRole('admin', 'web') && $user->can('admin.access');
        });
    }

    /**
     * Get the dashboards that should be listed in the Nova sidebar.
     *
     * @return array<int, \Laravel\Nova\Dashboard>
     */
    protected function dashboards(): array
    {
        return [
            new \App\Nova\Dashboards\Main,
        ];
    }

    /**
     * Get the tools that should be listed in the Nova sidebar.
     *
     * @return array<int, \Laravel\Nova\Tool>
     */
    public function tools(): array
    {
        return [];
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        parent::register();

        //
    }
}
