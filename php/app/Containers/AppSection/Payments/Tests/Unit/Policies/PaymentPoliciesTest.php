<?php

namespace App\Containers\AppSection\Payments\Tests\Unit\Policies;

use App\Containers\AppSection\Authorization\Models\Permission;
use App\Containers\AppSection\Payments\Models\Account;
use App\Containers\AppSection\Payments\Models\Agent;
use App\Containers\AppSection\Payments\Models\Charge;
use App\Containers\AppSection\Payments\Policies\AccountPolicy;
use App\Containers\AppSection\Payments\Policies\AgentPolicy;
use App\Containers\AppSection\Payments\Policies\ChargePolicy;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AccountPolicy::class)]
#[CoversClass(AgentPolicy::class)]
#[CoversClass(ChargePolicy::class)]
final class PaymentPoliciesTest extends TestCase
{
    public function testAccountPolicyRequiresMerchantManagementPermission(): void
    {
        $account = new Account();
        $allowed = $this->userWithPermission('merchants.manage');
        $denied = User::factory()->createOne();
        $policy = app(AccountPolicy::class);

        self::assertTrue($policy->viewAny($allowed));
        self::assertTrue($policy->view($allowed, $account));
        self::assertTrue($policy->create($allowed));
        self::assertTrue($policy->update($allowed, $account));
        self::assertTrue($policy->delete($allowed, $account));
        self::assertFalse($policy->viewAny($denied));
    }

    public function testAgentPolicyRequiresAgentManagementPermission(): void
    {
        $agent = new Agent();
        $allowed = $this->userWithPermission('agents.manage');
        $denied = User::factory()->createOne();
        $policy = app(AgentPolicy::class);

        self::assertTrue($policy->viewAny($allowed));
        self::assertTrue($policy->view($allowed, $agent));
        self::assertTrue($policy->create($allowed));
        self::assertTrue($policy->update($allowed, $agent));
        self::assertTrue($policy->delete($allowed, $agent));
        self::assertFalse($policy->viewAny($denied));
    }

    public function testChargePolicyUsesChargePermissionsAndDisallowsDelete(): void
    {
        $charge = new Charge();
        $viewer = $this->userWithPermission('charges.view');
        $creator = $this->userWithPermission('charges.create');
        $denied = User::factory()->createOne();
        $policy = app(ChargePolicy::class);

        self::assertTrue($policy->viewAny($viewer));
        self::assertTrue($policy->view($viewer, $charge));
        self::assertFalse($policy->create($viewer));
        self::assertTrue($policy->create($creator));
        self::assertTrue($policy->update($creator, $charge));
        self::assertFalse($policy->viewAny($denied));
        self::assertFalse($policy->delete($creator, $charge));
    }

    private function userWithPermission(string $permission): User
    {
        $user = User::factory()->createOne();
        $user->givePermissionTo(Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]));

        return $user;
    }
}
