<?php

namespace App\Nova\Tests\Unit;

use App\Ship\Middleware\EnsureNovaAdmin;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

final class EnsureNovaAdminTest extends TestCase
{
    public function testItAllowsAnAdminToAccessNova(): void
    {
        $request = $this->requestWithUser(true);

        $response = (new EnsureNovaAdmin())->handle($request, static fn (Request $request): string => 'allowed');

        self::assertSame('allowed', $response);
    }

    public function testItRejectsAndLogsOutANonAdminUser(): void
    {
        $request = $this->requestWithUser(false);
        $request->session()->put('authenticated', true);
        Auth::shouldReceive('logout')->once();

        $this->expectExceptionObject(new \Symfony\Component\HttpKernel\Exception\HttpException(403));

        try {
            (new EnsureNovaAdmin())->handle($request, static fn (Request $request): string => 'blocked');
        } finally {
            self::assertFalse($request->session()->has('authenticated'));
        }
    }

    public function testItRedirectsGuestsToTheNovaLogin(): void
    {
        config(['nova.path' => '/admin']);
        $request = Request::create('/admin');

        $response = (new EnsureNovaAdmin())->handle($request, static fn (Request $request): string => 'blocked');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(url('/admin/login'), $response->headers->get('Location'));
    }

    private function requestWithUser(bool $admin): Request
    {
        $user = Mockery::mock();
        $user->shouldReceive('hasRole')->with('admin', 'api')->andReturn($admin);
        $user->shouldReceive('can')->with('admin.access')->andReturn($admin);

        $request = Request::create('/admin');
        $request->setUserResolver(static fn () => $user);
        $request->setLaravelSession(new Store('test', new ArraySessionHandler(60)));

        return $request;
    }
}
