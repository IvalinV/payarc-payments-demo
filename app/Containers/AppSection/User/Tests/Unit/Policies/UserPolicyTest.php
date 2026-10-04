<?php

namespace App\Containers\AppSection\User\Tests\Unit\Policies;

use App\Containers\AppSection\Authorization\Models\Role;
use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\User\Policies\UserPolicy;
use App\Containers\AppSection\User\Tests\UnitTestCase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UserPolicy::class)]
final class UserPolicyTest extends UnitTestCase
{
    public function testCanDeleteUserOnlyIfAdmin(): void
    {
        $policy = app(UserPolicy::class);

        $this->assertFalse($policy->delete());
    }

    public function testCanShowUserOnlyIfAdmin(): void
    {
        $policy = app(UserPolicy::class);
        $user = User::factory()->createOne();

        $this->assertTrue($policy->show($user, $user));
    }

    public function testCanIndexUsersOnlyIfAdmin(): void
    {
        $policy = app(UserPolicy::class);

        $this->assertFalse($policy->index());
    }

    public function testCanUpdateUserAsOwner(): void
    {
        $policy = app(UserPolicy::class);
        $user = User::factory()->createOne();

        $this->assertTrue($policy->update($user, $user));
    }

    public function testWebAdminCanUpdateUsers(): void
    {
        $admin = User::factory()->createOne();
        $target = User::factory()->createOne();
        $admin->assignRole(Role::query()->firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]));

        $this->assertTrue(Gate::forUser($admin)->allows('update', $target));
    }
}
