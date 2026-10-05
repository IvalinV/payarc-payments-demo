<?php

namespace App\Ship\Tests\Unit\Seeders;

use App\Ship\Parents\Tests\TestCase;
use App\Ship\Seeders\InitialDeploymentDataSeeder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InitialDeploymentDataSeeder::class)]
final class InitialDeploymentDataSeederTest extends TestCase
{
    private const DEMO_EMAILS = [
        'admin@payarc.test',
        'agent1@payarc.test',
        'agent2@payarc.test',
        'merchant1@payarc.test',
        'merchant2@payarc.test',
        'merchant3@payarc.test',
        'norole@payarc.test',
    ];

    public function testDeploymentSeederCreatesTheRequiredDemoDataset(): void
    {
        $this->seed(InitialDeploymentDataSeeder::class);

        self::assertSame(7, DB::table('users')->whereIn('email', self::DEMO_EMAILS)->count());
        self::assertSame(2, DB::table('agents')->count());
        self::assertSame(6, DB::table('accounts')->count());
        self::assertSame(300, DB::table('charges')->count());
        self::assertSame(5, DB::table('agent_account')->count());
        self::assertSame(4, DB::table('account_user')->count());
        self::assertSame(2, DB::table('agent_user')->count());

        self::assertSame(3, DB::table('roles')->where('guard_name', 'web')->whereIn('name', ['admin', 'merchant', 'agent'])->count());
        self::assertSame(9, DB::table('permissions')->count());
        self::assertSame(1, DB::table('role_has_permissions')
            ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('roles.name', 'merchant')
            ->where('permissions.name', 'charges.capture')
            ->count());
        self::assertSame(5, DB::table('charges')->distinct('status')->count('status'));
        self::assertSame(1, DB::table('accounts')->where('status', 'suspended')->count());
    }

    public function testDeploymentSeederIsIdempotent(): void
    {
        $this->seed(InitialDeploymentDataSeeder::class);
        $this->seed(InitialDeploymentDataSeeder::class);

        self::assertSame(7, DB::table('users')->whereIn('email', self::DEMO_EMAILS)->count());
        self::assertSame(2, DB::table('agents')->count());
        self::assertSame(6, DB::table('accounts')->count());
        self::assertSame(300, DB::table('charges')->count());
        self::assertSame(300, DB::table('charges')->distinct('external_transaction_id')->count('external_transaction_id'));
    }

    public function testSeededChargesPreserveMoneyInvariants(): void
    {
        $this->seed(InitialDeploymentDataSeeder::class);

        $invalid = DB::table('charges')
            ->whereColumn('amount_refunded', '>', 'amount_captured')
            ->orWhereColumn('amount_captured', '>', 'amount')
            ->orWhere(function ($query) {
                $query->where('amount_voided', '!=', 0)
                    ->whereColumn('amount_voided', '!=', 'amount');
            })
            ->count();

        self::assertSame(0, $invalid);
    }

    public function testSeededChargesSpanNinetyDays(): void
    {
        $this->seed(InitialDeploymentDataSeeder::class);

        $first = CarbonImmutable::parse(DB::table('charges')->min('created_at'));
        $last = CarbonImmutable::parse(DB::table('charges')->max('created_at'));

        self::assertGreaterThanOrEqual(89, $first->diffInDays($last));
        self::assertLessThan(90, $first->diffInDays($last));
    }
}
