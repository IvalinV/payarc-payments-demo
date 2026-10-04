<?php

namespace App\Containers\AppSection\Payments\Tests\Unit\Models;

use App\Containers\AppSection\Payments\Models\Account;
use App\Containers\AppSection\Payments\Models\Agent;
use App\Containers\AppSection\Payments\Models\Charge;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Tests\TestCase;
use App\Ship\Seeders\InitialDeploymentDataSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Agent::class)]
#[CoversClass(Account::class)]
#[CoversClass(Charge::class)]
final class PaymentModelsTest extends TestCase
{
    public function testPaymentModelsExposeTheirRelationships(): void
    {
        $this->seed(InitialDeploymentDataSeeder::class);

        $agent = Agent::query()->where('email', 'agent2@payarc.test')->firstOrFail();
        $account = Account::query()->where('mid', 'MID_DEMO_001')->firstOrFail();
        $charge = Charge::query()->where('external_transaction_id', 'txn_demo_000001')->firstOrFail();
        $merchant = User::query()->where('email', 'merchant1@payarc.test')->firstOrFail();

        self::assertSame('agent1@payarc.test', $agent->parentAgent->email);
        self::assertTrue($agent->accounts->contains('id', $account->id) === false);
        self::assertTrue($account->agents->contains('email', 'agent1@payarc.test'));
        self::assertTrue($account->users->contains('email', $merchant->email));
        self::assertSame($account->id, $charge->account->id);
        self::assertSame(50, $account->charges()->count());
        self::assertTrue($merchant->accounts->contains('id', $account->id));
        self::assertCount(0, $merchant->agents);
    }

    public function testUserRoleChangesSynchronizeMirroredFlags(): void
    {
        $this->seed(InitialDeploymentDataSeeder::class);

        DB::table('users')->updateOrInsert(
            ['email' => 'observer-test@payarc.test'],
            [
                'name' => 'Observer Test',
                'password' => 'unused',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        $user = User::query()->where('email', 'observer-test@payarc.test')->firstOrFail();

        $user->syncRoles(['merchant']);
        $user->refresh();
        self::assertFalse($user->is_admin);
        self::assertFalse($user->is_agent);
        self::assertTrue($user->is_merchant);

        $user->assignRole('agent');
        $user->refresh();
        self::assertFalse($user->is_admin);
        self::assertTrue($user->is_agent);
        self::assertTrue($user->is_merchant);

        $user->syncRoles([]);
        $user->refresh();
        self::assertFalse($user->is_admin);
        self::assertFalse($user->is_agent);
        self::assertFalse($user->is_merchant);
    }
}
