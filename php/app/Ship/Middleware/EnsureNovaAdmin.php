<?php

namespace App\Ship\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Nova\Nova;

final class EnsureNovaAdmin
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->routeIs(
            'nova.pages.login',
            'nova.login',
            'nova.pages.password.*',
            'nova.password.*',
        )) {
            return $next($request);
        }

        $user = $request->user(config('nova.guard'));

        if ($user === null) {
            return redirect(Nova::url('/login'));
        }

        if (! $user->hasRole('admin', 'web') || ! $user->can('admin.access')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403);
        }

        return $next($request);
    }
}
