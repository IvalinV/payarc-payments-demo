<?php

namespace App\Nova\Tests\Functional;

use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Tests\TestCase;
use App\Ship\Seeders\InitialDeploymentDataSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

final class NovaAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(InitialDeploymentDataSeeder::class);
    }

    public function testAdminCanAccessNova(): void
    {
        $this->actingAs($this->user('admin@payarc.test'), 'web')
            ->get('/admin')
            ->assertRedirect(url('/admin/dashboards/main'));
    }

    /**
     * @dataProvider nonAdminUsers
     */
    public function testNonAdminSessionsAreRejectedFromNova(string $email): void
    {
        $this->actingAs($this->user($email), 'web')
            ->get('/admin')
            ->assertForbidden();

        $this->assertGuest('web');
    }

    public function testGuestIsRedirectedToNovaLogin(): void
    {
        $this->get('/admin')
            ->assertRedirect(url('/admin/login'));
    }

    /**
     * @dataProvider nonAdminUsers
     */
    public function testNonAdminCredentialsAreRejectedAtNovaLogin(string $email): void
    {
        $response = $this->withoutMiddleware(ValidateCsrfToken::class)->post('/admin/login', [
            'email' => $email,
            'password' => 'Demo#12345',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonAdminUsers(): array
    {
        return [
            'agent' => ['agent1@payarc.test'],
            'merchant' => ['merchant1@payarc.test'],
            'no role' => ['norole@payarc.test'],
        ];
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
